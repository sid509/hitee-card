<?php

namespace App\Http\Controllers\CardManagement;

use App\Models\CardManagement\Card;
use App\Models\CardManagement\CardInitializationOperation;
use App\Services\CardManagement\CardManagementError;
use App\Services\CardManagement\KeyServiceClient;
use App\Services\CardManagement\ResponseEnvelope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class InitializationController extends BaseCardManagementController
{
    public function getConfiguration(Request $request)
    {
        $uid = $request->query('uid');
        $allowlist = config('card_management.card_profile.lab_card_uid_allowlist', []);
        $currentCardUidAllowlisted = empty($allowlist)
            ? true  // Empty allowlist = all cards allowed
            : (bool) ($uid && in_array(strtoupper($uid), array_map('strtoupper', $allowlist)));

        return ResponseEnvelope::success([
            'environment' => config('card_management.card_profile.environment'),
            'physicalInitializationEnabled' => (bool) config('card_management.card_profile.physical_card_writes_enabled'),
            'secureKeyEnvelopeBridgeEnabled' => true,
            'reason' => null,
            'labProfile' => [
                'profileId' => config('card_management.card_profile.lab_profile.profile_id'),
                'cardCapacityKb' => config('card_management.card_profile.lab_profile.card_capacity_kb'),
                'ef0017Required' => config('card_management.card_profile.lab_profile.ef0017_required'),
                'currentCardUidAllowlisted' => $currentCardUidAllowlisted,
                'uidAllowlist' => $allowlist,
            ],
        ]);
    }

    public function getOperation(string $operationId)
    {
        $operation = $this->findOperation($operationId);
        return ResponseEnvelope::success(['operation' => $operation->toPublicArray()]);
    }

    public function prepareKeys(Request $request, string $operationId)
    {
        $operation = $this->findOperation($operationId);
        $this->assertVersion((int) $request->input('expectedVersion'), $operation->lock_version);

        if ($operation->status !== 'KEYS_PENDING') {
            throw new CardManagementError('INVALID_STATE_TRANSITION', 409, 'Operation must be in KEYS_PENDING.');
        }

        $recipientKeyId = $request->input('recipientKeyId');
        $recipientPublicKey = $request->input('recipientPublicKey');
        if (!$recipientKeyId || !$recipientPublicKey) {
            throw new CardManagementError('AMOUNT_INVALID', 400, 'recipientKeyId and recipientPublicKey are required.');
        }

        $card = Card::where('id', $operation->card_id)->firstOrFail();
        $fingerprint = hash('sha256', $recipientPublicKey);

        $keyService = KeyServiceClient::make();
        $result = $keyService->prepareKeyEnvelope([
            'requestId' => $this->requestId($request),
            'operationId' => $operation->id,
            'cardId' => $card->id,
            'expectedUid' => $operation->expected_uid,
            'cardNumber' => $card->card_number,
            'profileId' => $operation->key_profile_version,
            'workstationId' => $operation->workstation_id,
            'cityCode' => config('card_management.card_profile.lab_profile.city_code'),
            'issuerCode' => config('card_management.card_profile.lab_profile.issuer_code'),
            'recipient' => ['keyId' => $recipientKeyId, 'publicKey' => $recipientPublicKey],
        ], $this->requestId($request));

        DB::transaction(function () use ($operation, $result, $recipientKeyId, $fingerprint) {
            DB::table('cm_initialization_key_envelopes')->insert([
                'id' => Str::uuid()->toString(),
                'operation_id' => $operation->id,
                'key_service_request_id' => Str::uuid()->toString(),
                'recipient_key_id' => $recipientKeyId,
                'recipient_public_key_fingerprint' => strtoupper($fingerprint),
                'envelope_version' => $result['envelope']['version'] ?? 1,
                'envelope' => json_encode($result['envelope']),
                'expires_at' => $result['expiresAt'],
                'delivery_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $operation->update([
                'status' => 'KEYS_PREPARED',
                'current_step' => 'KEYS_PREPARED',
                'lock_version' => $operation->lock_version + 1,
                'key_manifest' => $result['manifest'],
                'key_service_request_id' => Str::uuid()->toString(),
            ]);
        });

        return ResponseEnvelope::success(['operation' => $operation->fresh()->toPublicArray()]);
    }

    public function getEnvelope(Request $request, string $operationId)
    {
        $operation = $this->findOperation($operationId);

        $envelopeRow = DB::table('cm_initialization_key_envelopes')
            ->where('operation_id', $operation->id)
            ->first();

        if (!$envelopeRow) {
            throw new CardManagementError('KEY_ENVELOPE_NOT_READY', 409, 'Key envelope not yet prepared.');
        }

        $maxDeliveries = (int) config('card_management.envelope.max_delivery_count');
        if ($envelopeRow->delivery_count >= $maxDeliveries) {
            throw new CardManagementError('KEY_ENVELOPE_ALREADY_DELIVERED', 409, 'Delivery limit exceeded.');
        }

        if (now()->gt($envelopeRow->expires_at)) {
            throw new CardManagementError('KEY_ENVELOPE_EXPIRED', 410, 'Key envelope TTL exceeded.');
        }

        // Block delivery after physical checkpoint
        $hasCheckpoint = DB::table('cm_card_initialization_checkpoints')
            ->where('operation_id', $operation->id)
            ->exists();
        if ($hasCheckpoint) {
            throw new CardManagementError('KEY_ENVELOPE_ALREADY_DELIVERED', 409, 'Envelope delivery blocked after physical checkpoint.');
        }

        DB::table('cm_initialization_key_envelopes')
            ->where('operation_id', $operation->id)
            ->update([
                'delivery_count' => $envelopeRow->delivery_count + 1,
                'first_delivered_at' => $envelopeRow->first_delivered_at ?? now(),
                'last_delivered_at' => now(),
                'updated_at' => now(),
            ]);

        $operation->update(['lock_version' => $operation->lock_version + 1]);

        return ResponseEnvelope::success([
            'keyEnvelope' => [
                'id' => $envelopeRow->id,
                'operationId' => $operation->id,
                'envelope' => json_decode($envelopeRow->envelope, true),
                'expiresAt' => \Carbon\Carbon::parse($envelopeRow->expires_at)->toIso8601ZuluString(),
                'deliveredAt' => now()->toIso8601ZuluString(),
                'deliveryCount' => $envelopeRow->delivery_count + 1,
            ],
            'operation' => $operation->fresh()->toPublicArray(),
        ]);
    }

    public function acknowledge(Request $request, string $operationId)
    {
        $operation = $this->findOperation($operationId);
        $recipientKeyId = $request->input('recipientKeyId');
        if (!$recipientKeyId) {
            throw new CardManagementError('AMOUNT_INVALID', 400, 'recipientKeyId is required.');
        }

        $updated = DB::table('cm_initialization_key_envelopes')
            ->where('operation_id', $operation->id)
            ->whereNull('acknowledged_at')
            ->update(['acknowledged_at' => now(), 'updated_at' => now()]);

        if ($updated === 0) {
            throw new CardManagementError('KEY_ENVELOPE_ALREADY_DELIVERED', 409, 'Envelope already acknowledged.');
        }

        $operation->update(['lock_version' => $operation->lock_version + 1]);

        return ResponseEnvelope::success(['operation' => $operation->fresh()->toPublicArray()]);
    }

    public function authorizePhysicalWrite(Request $request, string $operationId)
    {
        $operation = $this->findOperation($operationId);
        $this->assertVersion((int) $request->input('expectedVersion'), $operation->lock_version);

        if ($operation->status !== 'KEYS_PREPARED') {
            throw new CardManagementError('INVALID_STATE_TRANSITION', 409, 'Operation must be in KEYS_PREPARED.');
        }

        $phrase = $request->input('confirmationPhrase');
        if (!$phrase) {
            throw new CardManagementError('INVALID_CONFIRMATION_PHRASE', 403, 'confirmationPhrase is required.');
        }

        // Store SHA-256 digest of the phrase
        $digest = hash('sha256', $phrase);

        $operation->update([
            'status' => 'AUTHORIZED',
            'operator_confirmation_sha256' => $digest,
            'physical_write_authorized_at' => now(),
            'lock_version' => $operation->lock_version + 1,
        ]);

        return ResponseEnvelope::success(['operation' => $operation->fresh()->toPublicArray()]);
    }

    public function checkpoint(Request $request, string $operationId)
    {
        $operation = $this->findOperation($operationId);
        $this->assertVersion((int) $request->input('expectedVersion'), $operation->lock_version);

        $step = $request->input('step');
        $metadata = $request->input('resultMetadata', []);

        $canonicalSteps = [
            'FACTORY_AUTHENTICATED', 'CARD_FORMATTED', 'MF_KEY_FILE_CREATED',
            'DCCK_INSTALLED', 'DCMK_INSTALLED', 'MF_FILES_CREATED', 'ADF_CREATED',
            'ADF_KEY_FILE_CREATED', 'APPLICATION_KEYS_INSTALLED', 'APPLICATION_FILES_CREATED',
            'INITIAL_DATA_WRITTEN', 'VERIFICATION_STARTED', 'VERIFIED',
        ];

        if (!in_array($step, $canonicalSteps, true)) {
            throw new CardManagementError('AMOUNT_INVALID', 400, 'Unknown checkpoint step.');
        }

        // Enforce ordering
        $existingSteps = DB::table('cm_card_initialization_checkpoints')
            ->where('operation_id', $operation->id)
            ->pluck('step')
            ->toArray();

        $stepIndex = array_search($step, $canonicalSteps, true);
        foreach ($canonicalSteps as $i => $s) {
            if ($i < $stepIndex && !in_array($s, $existingSteps, true)) {
                throw new CardManagementError('INVALID_STATE_TRANSITION', 409, "Checkpoint step {$s} must be recorded before {$step}.");
            }
        }

        $sequenceNo = count($existingSteps) + 1;

        DB::table('cm_card_initialization_checkpoints')->insert([
            'id' => Str::uuid()->toString(),
            'operation_id' => $operation->id,
            'step' => $step,
            'sequence_no' => $sequenceNo,
            'workstation_id' => $operation->workstation_id,
            'result_metadata' => json_encode($metadata),
            'created_at' => now(),
        ]);

        $newStatus = $operation->status;
        if ($operation->status === 'AUTHORIZED') {
            $newStatus = 'IN_PROGRESS';
        }
        if ($step === 'VERIFICATION_STARTED') {
            $newStatus = 'VERIFYING';
        }

        $operation->update([
            'status' => $newStatus,
            'current_step' => $step,
            'last_successful_step' => $step,
            'lock_version' => $operation->lock_version + 1,
        ]);

        return ResponseEnvelope::success([
            'checkpoint' => [
                'id' => Str::uuid()->toString(),
                'operationId' => $operation->id,
                'step' => $step,
                'status' => 'SUCCESS',
                'metadata' => $metadata,
                'recordedAt' => now()->toIso8601ZuluString(),
            ],
            'operation' => $operation->fresh()->toPublicArray(),
        ]);
    }

    public function complete(Request $request, string $operationId)
    {
        $operation = $this->findOperation($operationId);
        $this->assertVersion((int) $request->input('expectedVersion'), $operation->lock_version);

        if ($operation->status !== 'VERIFYING') {
            throw new CardManagementError('INVALID_STATE_TRANSITION', 409, 'Operation must be in VERIFYING.');
        }

        DB::transaction(function () use ($operation) {
            $operation->update([
                'status' => 'SUCCEEDED',
                'current_step' => 'COMPLETED',
                'completed_at' => now(),
                'lock_version' => $operation->lock_version + 1,
            ]);

            Card::where('id', $operation->card_id)->update([
                'status' => 'INITIALIZED',
                'initialized_at' => now(),
                'installed_key_profile_version' => $operation->key_profile_version,
                'installed_card_structure_version' => $operation->card_structure_version,
            ]);
        });

        $card = Card::find($operation->card_id);
        return ResponseEnvelope::success([
            'operation' => $operation->fresh()->toPublicArray(),
            'card' => $card->toPublicArray(),
        ]);
    }

    public function fail(Request $request, string $operationId)
    {
        $operation = $this->findOperation($operationId);
        $this->assertVersion((int) $request->input('expectedVersion'), $operation->lock_version);

        $failureCode = $request->input('failureCode');
        $failureMessage = $request->input('failureMessage');
        $physicalStateUncertain = (bool) $request->input('physicalStateUncertain', false);

        if (!$failureCode || !preg_match('/^[A-Z][A-Z0-9_]{2,63}$/', $failureCode)) {
            throw new CardManagementError('AMOUNT_INVALID', 400, 'failureCode must match ^[A-Z][A-Z0-9_]{2,63}$.');
        }
        if (!$failureMessage || strlen($failureMessage) > 1000) {
            throw new CardManagementError('AMOUNT_INVALID', 400, 'failureMessage is required (max 1000 chars).');
        }

        $newStatus = $physicalStateUncertain ? 'AMBIGUOUS' : 'FAILED';

        $operation->update([
            'status' => $newStatus,
            'failure_code' => $failureCode,
            'failure_message' => $failureMessage,
            'last_attempted_step' => $request->input('lastAttemptedStep'),
            'physical_state_uncertain' => $physicalStateUncertain,
            'failed_at' => now(),
            'lock_version' => $operation->lock_version + 1,
        ]);

        return ResponseEnvelope::success(['operation' => $operation->fresh()->toPublicArray()]);
    }

    public function cancel(Request $request, string $operationId)
    {
        $operation = $this->findOperation($operationId);
        $this->assertVersion((int) $request->input('expectedVersion'), $operation->lock_version);

        // Cancellation blocked after physical write boundary (any checkpoint at or after CARD_FORMATTED)
        $hasPhysicalWrite = DB::table('cm_card_initialization_checkpoints')
            ->where('operation_id', $operation->id)
            ->whereIn('step', ['CARD_FORMATTED', 'MF_KEY_FILE_CREATED', 'DCCK_INSTALLED', 'DCMK_INSTALLED', 'MF_FILES_CREATED', 'ADF_CREATED', 'ADF_KEY_FILE_CREATED', 'APPLICATION_KEYS_INSTALLED', 'APPLICATION_FILES_CREATED', 'INITIAL_DATA_WRITTEN', 'VERIFICATION_STARTED', 'VERIFIED'])
            ->exists();

        if ($hasPhysicalWrite || in_array($operation->status, ['IN_PROGRESS', 'VERIFYING', 'AMBIGUOUS', 'RECOVERY_REQUIRED'], true)) {
            throw new CardManagementError('INVALID_STATE_TRANSITION', 409, 'Cancellation blocked after physical write boundary.');
        }

        $operation->update([
            'status' => 'CANCELLED',
            'current_step' => 'CANCELLED',
            'lock_version' => $operation->lock_version + 1,
        ]);

        return ResponseEnvelope::success(['operation' => $operation->fresh()->toPublicArray()]);
    }

    private function findOperation(string $id): CardInitializationOperation
    {
        $operation = CardInitializationOperation::find($id);
        if (!$operation) {
            throw new CardManagementError('OPERATION_NOT_FOUND', 404, 'Operation not found.');
        }
        return $operation;
    }
}
