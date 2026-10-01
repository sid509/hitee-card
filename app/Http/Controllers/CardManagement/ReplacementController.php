<?php

namespace App\Http\Controllers\CardManagement;

use App\Models\CardManagement\Card;
use App\Services\CardManagement\CardManagementError;
use App\Services\CardManagement\KeyServiceClient;
use App\Services\CardManagement\OperationService;
use App\Services\CardManagement\ResponseEnvelope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Replacement controller.
 * Implements card replacement per PRODUCTION_BACKEND_API_REQUIREMENTS §5.7.
 *
 * Full CRUD implementation with balance evidence, transfer tracking,
 * optimistic locking, key envelope delivery, and checkpoints.
 */
final class ReplacementController extends BaseCardManagementController
{
    private OperationService $operations;

    public function __construct()
    {
        $this->operations = new OperationService(
            'cm_card_replacement_operations',
            'cm_card_replacement_checkpoints',
            'cm_card_replacement_key_envelopes',
        );
    }

    public function getConfiguration(Request $request)
    {
        return ResponseEnvelope::success([
            'ready' => (bool) config('card_management.replacement.lab_replacement_writes_enabled', true),
            'labReplacementWritesEnabled' => (bool) config('card_management.replacement.lab_replacement_writes_enabled', true),
            'reason' => null,
            'oldCard' => null,
            'profileSnapshot' => [
                'cityCode' => '0000000000000001',
                'issuerCode' => '0000000000000001',
                'cardDataVersion' => 1,
                'cardEnableFlag' => true,
                'applicationIdentifier' => 'A000000001',
                'applicationVersion' => 1,
                'applicationType' => 1,
                'applicationEnableFlag' => true,
            ],
        ]);
    }

    public function createReplacement(Request $request, string $uid)
    {
        $validated = $request->validate([
            'idempotencyKey' => 'required|string|max:128',
            'oldCardReference' => 'required|string|max:32',
            'oldCardId' => 'nullable|string',
            'reason' => 'required|string|in:LOST,DAMAGED,EXPIRED,DEFECTIVE',
            'notes' => 'nullable|string|max:500',
            'approvedTransferAmountMinorUnits' => 'nullable|integer|min:0',
            'cardTypeCode' => 'required|string|size:4',
            'applicationType' => 'required|string|size:2',
            'depositMinorUnits' => 'nullable|integer|min:0',
            'enableDate' => 'required|date',
            'expiryDate' => 'required|date|after:enableDate',
        ]);

        $card = Card::where('uid', $uid)->first();
        if (!$card) {
            throw new CardManagementError('CARD_NOT_REGISTERED', 404, 'Card not found.');
        }

        $operation = $this->operations->create([
            'new_card_id' => $card->id,
            'old_card_reference' => $validated['oldCardReference'],
            'old_card_id' => $validated['oldCardId'] ?? null,
            'reason' => $validated['reason'],
            'notes' => $validated['notes'] ?? null,
            'approved_transfer_amount_minor_units' => $validated['approvedTransferAmountMinorUnits'] ?? 0,
            'idempotency_key' => $validated['idempotencyKey'],
            'expected_uid' => $uid,
            'card_number' => $card->card_number,
            'workstation_id' => $this->workstationId($request),
            'card_type_code' => $validated['cardTypeCode'],
            'application_type' => $validated['applicationType'],
            'deposit_minor_units' => $validated['depositMinorUnits'] ?? 0,
            'enable_date' => $validated['enableDate'],
            'expiry_date' => $validated['expiryDate'],
            'status' => 'CREATED',
            'transfer_status' => ($validated['approvedTransferAmountMinorUnits'] ?? 0) > 0 ? 'PENDING' : 'NOT_REQUIRED',
            'key_profile_version' => $card->key_profile_version ?? 'hitee-lab-v1',
        ]);

        return ResponseEnvelope::success(
            ['operation' => $this->operations->toPublicArray($operation)],
            201
        );
    }

    public function getActiveByCard(string $uid)
    {
        $card = Card::where('uid', $uid)->first();
        if (!$card) {
            return ResponseEnvelope::success(['card' => null, 'operation' => null]);
        }

        $operation = $this->operations->findActiveByCard($card->id);
        return ResponseEnvelope::success([
            'card' => ['uid' => $card->uid, 'cardNumber' => $card->card_number],
            'operation' => $operation ? $this->operations->toPublicArray($operation) : null,
        ]);
    }

    public function getHistoryByCard(Request $request, string $uid)
    {
        $card = Card::where('uid', $uid)->first();
        if (!$card) {
            return ResponseEnvelope::success(['card' => null, 'operations' => []]);
        }

        $operations = $this->operations->findByCard($card->id);
        return ResponseEnvelope::success([
            'card' => ['uid' => $card->uid, 'cardNumber' => $card->card_number],
            'operations' => array_map(fn ($op) => $this->operations->toPublicArray($op), $operations),
        ]);
    }

    public function getOperation(string $operationId)
    {
        $operation = $this->operations->findOrFail($operationId);
        return ResponseEnvelope::success(['operation' => $this->operations->toPublicArray($operation)]);
    }

    public function recordBalanceEvidence(Request $request, string $operationId)
    {
        $operation = $this->operations->findOrFail($operationId);
        $this->assertVersion((int) $request->input('expectedVersion'), $operation->lock_version);

        $validated = $request->validate([
            'balanceMinorUnits' => 'required|integer',
            'offlineCounter' => 'nullable|integer',
            'evidence' => 'nullable|array',
        ]);

        $evidence = [
            'balanceMinorUnits' => $validated['balanceMinorUnits'],
            'offlineCounter' => $validated['offlineCounter'] ?? null,
            'capturedAt' => now()->toIso8601ZuluString(),
            'extra' => $validated['evidence'] ?? null,
        ];

        DB::table('cm_card_replacement_operations')->where('id', $operation->id)->update([
            'balance_evidence' => json_encode($evidence),
            'lock_version' => $operation->lock_version + 1,
            'updated_at' => now(),
        ]);

        $this->operations->recordCheckpoint($operation->id, 'BALANCE_EVIDENCE_RECORDED', $evidence);

        return ResponseEnvelope::success([
            'operation' => $this->operations->toPublicArray($this->operations->findOrFail($operation->id)),
        ]);
    }

    public function prepareKeys(Request $request, string $operationId)
    {
        $operation = $this->operations->findOrFail($operationId);
        $this->assertVersion((int) $request->input('expectedVersion'), $operation->lock_version);

        if (!in_array($operation->status, ['CREATED', 'KEYS_PENDING'])) {
            throw new CardManagementError('INVALID_STATE_TRANSITION', 409, 'Operation must be in CREATED or KEYS_PENDING.');
        }

        $recipientKeyId = $request->input('recipientKeyId');
        $recipientPublicKey = $request->input('recipientPublicKey');
        if (!$recipientKeyId || !$recipientPublicKey) {
            throw new CardManagementError('AMOUNT_INVALID', 400, 'recipientKeyId and recipientPublicKey are required.');
        }

        $card = Card::where('id', $operation->new_card_id)->firstOrFail();
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

        DB::table('cm_card_replacement_key_envelopes')->insert([
            'operation_id' => $operation->id,
            'key_service_request_id' => Str::uuid()->toString(),
            'recipient_key_id' => $recipientKeyId,
            'recipient_fingerprint' => strtoupper($fingerprint),
            'envelope_version' => $result['envelope']['version'] ?? 1,
            'envelope' => json_encode($result['envelope']),
            'expires_at' => $result['expiresAt'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $updated = $this->operations->updateStatus($operation->id, 'KEYS_PREPARED', $operation->lock_version, 'KEYS_PREPARED');

        return ResponseEnvelope::success(['operation' => $this->operations->toPublicArray($updated)]);
    }

    public function authorize(Request $request, string $operationId)
    {
        $operation = $this->operations->findOrFail($operationId);
        $this->assertVersion((int) $request->input('expectedVersion'), $operation->lock_version);

        if ($operation->status !== 'KEYS_PREPARED') {
            throw new CardManagementError('INVALID_STATE_TRANSITION', 409, 'Operation must be in KEYS_PREPARED.');
        }

        $phrase = $request->input('confirmationPhrase');
        if (!$phrase) {
            throw new CardManagementError('INVALID_CONFIRMATION_PHRASE', 403, 'confirmationPhrase is required.');
        }

        DB::table('cm_card_replacement_operations')->where('id', $operation->id)->update([
            'status' => 'AUTHORIZED',
            'expected_confirmation_sha256' => hash('sha256', $phrase),
            'physical_write_authorized_at' => now(),
            'lock_version' => $operation->lock_version + 1,
            'updated_at' => now(),
        ]);

        return ResponseEnvelope::success([
            'operation' => $this->operations->toPublicArray($this->operations->findOrFail($operation->id)),
        ]);
    }

    public function getEnvelope(string $operationId)
    {
        $operation = $this->operations->findOrFail($operationId);

        $envelopeRow = DB::table('cm_card_replacement_key_envelopes')
            ->where('operation_id', $operation->id)
            ->first();

        if (!$envelopeRow) {
            throw new CardManagementError('KEY_ENVELOPE_NOT_READY', 409, 'Key envelope not yet prepared.');
        }

        if (now()->gt($envelopeRow->expires_at)) {
            throw new CardManagementError('KEY_ENVELOPE_EXPIRED', 410, 'Key envelope TTL exceeded.');
        }

        return ResponseEnvelope::success([
            'keyEnvelope' => [
                'operationId' => $operation->id,
                'envelope' => json_decode($envelopeRow->envelope, true),
                'expiresAt' => \Carbon\Carbon::parse($envelopeRow->expires_at)->toIso8601ZuluString(),
            ],
            'operation' => $this->operations->toPublicArray($operation),
        ]);
    }

    public function acknowledgeEnvelope(Request $request, string $operationId)
    {
        $operation = $this->operations->findOrFail($operationId);

        DB::table('cm_card_replacement_key_envelopes')
            ->where('operation_id', $operation->id)
            ->whereNull('acknowledged_at')
            ->update(['acknowledged_at' => now(), 'updated_at' => now()]);

        return ResponseEnvelope::success([
            'operation' => $this->operations->toPublicArray($operation),
        ]);
    }

    public function checkpoint(Request $request, string $operationId)
    {
        $operation = $this->operations->findOrFail($operationId);
        $step = $request->input('step');
        $metadata = $request->input('metadata');

        $this->operations->recordCheckpoint($operation->id, $step, $metadata);

        return ResponseEnvelope::success([
            'operation' => $this->operations->toPublicArray($operation),
            'checkpoints' => array_map(fn ($c) => [
                'step' => $c->step,
                'sequenceNo' => $c->sequence_no,
            ], $this->operations->getCheckpoints($operation->id)),
        ]);
    }

    public function complete(Request $request, string $operationId)
    {
        $operation = $this->operations->findOrFail($operationId);
        $this->assertVersion((int) $request->input('expectedVersion'), $operation->lock_version);

        $completed = $this->operations->complete($operation->id, $operation->lock_version);
        $this->operations->recordCheckpoint($operation->id, 'COMPLETED');

        // Update new card status to ACTIVE
        DB::table('cm_cards')->where('id', $operation->new_card_id)->update([
            'status' => 'ACTIVE',
            'updated_at' => now(),
        ]);

        // Block old card if old_card_id is set
        if ($operation->old_card_id) {
            DB::table('cm_cards')->where('id', $operation->old_card_id)->update([
                'status' => 'BLOCKED',
                'updated_at' => now(),
            ]);

            DB::table('cm_card_lifecycle_events')->insert([
                'id' => Str::uuid()->toString(),
                'card_id' => $operation->old_card_id,
                'from_status' => 'ACTIVE',
                'to_status' => 'BLOCKED',
                'reason' => 'Replaced by ' . $operation->new_card_id,
                'workstation_id' => $operation->workstation_id,
                'created_at' => now(),
            ]);
        }

        DB::table('cm_card_lifecycle_events')->insert([
            'id' => Str::uuid()->toString(),
            'card_id' => $operation->new_card_id,
            'from_status' => 'ISSUED',
            'to_status' => 'ACTIVE',
            'reason' => 'Replacement completed',
            'workstation_id' => $operation->workstation_id,
            'created_at' => now(),
        ]);

        return ResponseEnvelope::success(['operation' => $this->operations->toPublicArray($completed)]);
    }

    public function cancel(Request $request, string $operationId)
    {
        $operation = $this->operations->findOrFail($operationId);
        $this->assertVersion((int) $request->input('expectedVersion'), $operation->lock_version);

        $cancelled = $this->operations->cancel($operation->id, $operation->lock_version);

        return ResponseEnvelope::success(['operation' => $this->operations->toPublicArray($cancelled)]);
    }

    public function fail(Request $request, string $operationId)
    {
        $operation = $this->operations->findOrFail($operationId);
        $this->assertVersion((int) $request->input('expectedVersion'), $operation->lock_version);

        $failed = $this->operations->fail(
            $operation->id,
            $operation->lock_version,
            $request->input('failureCode', 'UNKNOWN'),
            $request->input('failureMessage', 'Replacement failed.'),
        );

        return ResponseEnvelope::success(['operation' => $this->operations->toPublicArray($failed)]);
    }

    public function prepareTransfer(Request $request, string $operationId)
    {
        $operation = $this->operations->findOrFail($operationId);
        $this->assertVersion((int) $request->input('expectedVersion'), $operation->lock_version);

        if ($operation->transfer_status !== 'PENDING') {
            throw new CardManagementError('INVALID_STATE_TRANSITION', 409, 'Transfer must be in PENDING status.');
        }

        DB::table('cm_card_replacement_operations')->where('id', $operation->id)->update([
            'transfer_status' => 'PREPARED',
            'lock_version' => $operation->lock_version + 1,
            'updated_at' => now(),
        ]);

        $this->operations->recordCheckpoint($operation->id, 'TRANSFER_PREPARED');

        return ResponseEnvelope::success([
            'operation' => $this->operations->toPublicArray($this->operations->findOrFail($operation->id)),
        ]);
    }

    public function reconcileTransfer(Request $request, string $operationId)
    {
        $operation = $this->operations->findOrFail($operationId);
        $this->assertVersion((int) $request->input('expectedVersion'), $operation->lock_version);

        $validated = $request->validate([
            'transferredAmountMinorUnits' => 'required|integer',
            'reconciled' => 'required|boolean',
        ]);

        DB::table('cm_card_replacement_operations')->where('id', $operation->id)->update([
            'transfer_status' => $validated['reconciled'] ? 'COMPLETED' : 'FAILED',
            'approved_transfer_amount_minor_units' => $validated['transferredAmountMinorUnits'],
            'physical_state_uncertain' => !$validated['reconciled'],
            'lock_version' => $operation->lock_version + 1,
            'updated_at' => now(),
        ]);

        $this->operations->recordCheckpoint($operation->id, 'TRANSFER_RECONCILED', $validated);

        return ResponseEnvelope::success([
            'operation' => $this->operations->toPublicArray($this->operations->findOrFail($operation->id)),
        ]);
    }
}
