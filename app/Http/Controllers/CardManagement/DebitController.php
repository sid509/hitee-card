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
 * Debit controller.
 * Implements the debit operation lifecycle per PRODUCTION_BACKEND_API_REQUIREMENTS §5.4.
 *
 * Phases 10-12: Full CRUD implementation with pessimistic locking,
 * key envelope delivery, checkpoints, and reconciliation.
 */
final class DebitController extends BaseCardManagementController
{
    private OperationService $operations;

    public function __construct()
    {
        $this->operations = new OperationService(
            'cm_wallet_debit_operations',
            'cm_wallet_debit_checkpoints',
            'cm_wallet_debit_key_envelopes',
        );
    }

    public function getConfiguration(Request $request)
    {
        return ResponseEnvelope::success([
            'environment' => config('card_management.card_profile.environment'),
            'physicalDebitEnabled' => (bool) config('card_management.debit.physical_debit_enabled', true),
            'labDebitWritesEnabled' => (bool) config('card_management.debit.physical_debit_enabled', true),
            'secureKeyEnvelopeBridgeEnabled' => true,
            'maxAmountMinorUnits' => (int) config('card_management.debit.max_amount_minor_units', 50000),
            'reason' => null,
        ]);
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

    public function createDebit(Request $request, string $uid)
    {
        $validated = $request->validate([
            'amountMinorUnits' => 'required|integer|min:1',
            'idempotencyKey' => 'required|string|max:128',
            'terminalNumberHex' => 'required|string|size:12',
            'terminalTransactionSequence' => 'required|integer',
        ]);

        $card = Card::where('uid', $uid)->first();
        if (!$card) {
            throw new CardManagementError('CARD_NOT_REGISTERED', 404, 'Card not found.');
        }

        $operation = $this->operations->create([
            'card_id' => $card->id,
            'idempotency_key' => $validated['idempotencyKey'],
            'expected_uid' => $uid,
            'card_number' => $card->card_number,
            'workstation_id' => $this->workstationId($request),
            'amount_minor_units' => $validated['amountMinorUnits'],
            'terminal_number_hex' => $validated['terminalNumberHex'],
            'terminal_transaction_sequence' => $validated['terminalTransactionSequence'],
            'key_profile_version' => $card->key_profile_version ?? 'hitee-lab-v1',
        ]);

        return ResponseEnvelope::success(
            ['operation' => $this->operations->toPublicArray($operation)],
            201
        );
    }

    public function getDebit(string $operationId)
    {
        $operation = $this->operations->findOrFail($operationId);
        return ResponseEnvelope::success(['operation' => $this->operations->toPublicArray($operation)]);
    }

    public function prepareKeys(Request $request, string $operationId)
    {
        $operation = $this->operations->findOrFail($operationId);
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

        DB::table('cm_wallet_debit_key_envelopes')->insert([
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

        DB::table('cm_wallet_debit_operations')->where('id', $operation->id)->update([
            'status' => 'AUTHORIZED',
            'operator_confirmation_sha256' => hash('sha256', $phrase),
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

        $envelopeRow = DB::table('cm_wallet_debit_key_envelopes')
            ->where('operation_id', $operation->id)
            ->first();

        if (!$envelopeRow) {
            throw new CardManagementError('KEY_ENVELOPE_NOT_READY', 409, 'Key envelope not yet prepared.');
        }

        $maxDeliveries = (int) config('card_management.envelope.max_delivery_count', 3);
        if ($envelopeRow->delivery_count >= $maxDeliveries) {
            throw new CardManagementError('KEY_ENVELOPE_ALREADY_DELIVERED', 409, 'Delivery limit exceeded.');
        }

        if (now()->gt($envelopeRow->expires_at)) {
            throw new CardManagementError('KEY_ENVELOPE_EXPIRED', 410, 'Key envelope TTL exceeded.');
        }

        DB::table('cm_wallet_debit_key_envelopes')
            ->where('operation_id', $operation->id)
            ->update([
                'delivery_count' => $envelopeRow->delivery_count + 1,
                'first_delivered_at' => $envelopeRow->first_delivered_at ?? now(),
                'last_delivered_at' => now(),
                'updated_at' => now(),
            ]);

        return ResponseEnvelope::success([
            'keyEnvelope' => [
                'id' => $envelopeRow->id,
                'operationId' => $operation->id,
                'envelope' => json_decode($envelopeRow->envelope, true),
                'expiresAt' => \Carbon\Carbon::parse($envelopeRow->expires_at)->toIso8601ZuluString(),
                'deliveryCount' => $envelopeRow->delivery_count + 1,
            ],
            'operation' => $this->operations->toPublicArray($operation),
        ]);
    }

    public function acknowledgeEnvelope(Request $request, string $operationId)
    {
        $operation = $this->operations->findOrFail($operationId);

        $updated = DB::table('cm_wallet_debit_key_envelopes')
            ->where('operation_id', $operation->id)
            ->whereNull('acknowledged_at')
            ->update(['acknowledged_at' => now(), 'updated_at' => now()]);

        if ($updated === 0) {
            throw new CardManagementError('KEY_ENVELOPE_ALREADY_DELIVERED', 409, 'Envelope already acknowledged.');
        }

        return ResponseEnvelope::success([
            'operation' => $this->operations->toPublicArray($operation),
        ]);
    }

    public function attemptDebit(Request $request, string $operationId)
    {
        $operation = $this->operations->findOrFail($operationId);
        $this->assertVersion((int) $request->input('expectedVersion'), $operation->lock_version);

        if ($operation->status !== 'AUTHORIZED') {
            throw new CardManagementError('INVALID_STATE_TRANSITION', 409, 'Operation must be AUTHORIZED.');
        }

        $validated = $request->validate([
            'balanceBefore' => 'required|integer',
            'balanceAfter' => 'required|integer',
            'transactionDateTime' => 'required|string|size:14',
            'offlineCounterBefore' => 'nullable|integer',
            'offlineCounterAfter' => 'nullable|integer',
        ]);

        DB::table('cm_wallet_debit_operations')->where('id', $operation->id)->update([
            'status' => 'DEBIT_PENDING_VERIFICATION',
            'debit_command_attempted' => true,
            'balance_before' => $validated['balanceBefore'],
            'balance_after' => $validated['balanceAfter'],
            'offline_counter_before' => $validated['offlineCounterBefore'] ?? null,
            'offline_counter_after' => $validated['offlineCounterAfter'] ?? null,
            'transaction_datetime' => $validated['transactionDateTime'],
            'lock_version' => $operation->lock_version + 1,
            'updated_at' => now(),
        ]);

        $this->operations->recordCheckpoint($operation->id, 'DEBIT_ATTEMPTED', $validated);

        return ResponseEnvelope::success([
            'operation' => $this->operations->toPublicArray($this->operations->findOrFail($operation->id)),
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

        $extra = [];
        if ($request->has('tacHex')) $extra['tac_hex'] = $request->input('tacHex');
        if ($request->has('tacVerified')) $extra['tac_verified'] = $request->input('tacVerified');
        if ($request->has('mac2Hex')) $extra['mac2_hex'] = $request->input('mac2Hex');
        if ($request->has('mac2Verified')) $extra['mac2_verified'] = $request->input('mac2Verified');
        if ($request->has('proofRetrieved')) $extra['proof_retrieved'] = $request->input('proofRetrieved');

        $completed = $this->operations->complete($operation->id, $operation->lock_version, $extra);
        $this->operations->recordCheckpoint($operation->id, 'COMPLETED');

        return ResponseEnvelope::success(['operation' => $this->operations->toPublicArray($completed)]);
    }

    public function fail(Request $request, string $operationId)
    {
        $operation = $this->operations->findOrFail($operationId);
        $this->assertVersion((int) $request->input('expectedVersion'), $operation->lock_version);

        $failed = $this->operations->fail(
            $operation->id,
            $operation->lock_version,
            $request->input('failureCode', 'UNKNOWN'),
            $request->input('failureMessage', 'Operation failed.'),
        );

        return ResponseEnvelope::success(['operation' => $this->operations->toPublicArray($failed)]);
    }

    public function cancel(Request $request, string $operationId)
    {
        $operation = $this->operations->findOrFail($operationId);
        $this->assertVersion((int) $request->input('expectedVersion'), $operation->lock_version);

        $cancelled = $this->operations->cancel($operation->id, $operation->lock_version);

        return ResponseEnvelope::success(['operation' => $this->operations->toPublicArray($cancelled)]);
    }
}
