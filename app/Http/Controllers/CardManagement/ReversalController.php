<?php

namespace App\Http\Controllers\CardManagement;

use App\Models\CardManagement\Card;
use App\Services\CardManagement\CardManagementError;
use App\Services\CardManagement\OperationService;
use App\Services\CardManagement\ResponseEnvelope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Reversal controller.
 * Implements the recharge reversal operation lifecycle per §5.5.
 *
 * A reversal undoes a previous recharge operation. This is a double-entry
 * ledger transaction — the original recharge must be locked during the
 * reversal to prevent race conditions.
 */
final class ReversalController extends BaseCardManagementController
{
    private OperationService $operations;

    public function __construct()
    {
        $this->operations = new OperationService(
            'cm_wallet_recharge_reversal_operations',
            'cm_wallet_recharge_reversal_checkpoints',
            'cm_wallet_recharge_reversal_key_envelopes',
        );
    }

    public function getConfiguration(Request $request)
    {
        return ResponseEnvelope::success([
            'environment' => config('card_management.card_profile.environment'),
            'physicalReversalEnabled' => (bool) config('card_management.reversal.physical_reversal_enabled', true),
            'labRechargeReversalWritesEnabled' => (bool) config('card_management.reversal.physical_reversal_enabled', true),
            'secureKeyEnvelopeBridgeEnabled' => true,
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

    public function createReversal(Request $request, string $uid)
    {
        $validated = $request->validate([
            'originalRechargeOperationId' => 'required|string',
            'amountMinorUnits' => 'required|integer|min:1',
            'idempotencyKey' => 'required|string|max:128',
            'terminalNumberHex' => 'required|string|size:12',
            'terminalTransactionSequence' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:256',
        ]);

        $card = Card::where('uid', $uid)->first();
        if (!$card) {
            throw new CardManagementError('CARD_NOT_REGISTERED', 404, 'Card not found.');
        }

        // Lock the original recharge, guard against double-reversal, and create
        // the reversal row atomically — a concurrent request must not pass the
        // existing-reversal check before our insert commits.
        $workstationId = $this->workstationId($request);
        $operation = DB::transaction(function () use ($validated, $card, $uid, $workstationId) {
            $originalRecharge = DB::table('cm_wallet_recharge_operations')
                ->where('id', $validated['originalRechargeOperationId'])
                ->where('card_id', $card->id)
                ->lockForUpdate()
                ->first();

            if (!$originalRecharge) {
                throw new CardManagementError('OPERATION_NOT_FOUND', 404, 'Original recharge operation not found.');
            }

            if ($originalRecharge->status !== 'COMPLETED') {
                throw new CardManagementError('INVALID_STATE_TRANSITION', 409, 'Can only reverse COMPLETED recharges.');
            }

            if ((int) $validated['amountMinorUnits'] > (int) $originalRecharge->amount_minor_units) {
                throw new CardManagementError('AMOUNT_INVALID', 400, 'Reversal amount exceeds the original recharge amount.');
            }

            // Any prior reversal that is not in a terminal state — or already
            // completed — blocks a second reversal for the same recharge.
            $existingReversal = DB::table('cm_wallet_recharge_reversal_operations')
                ->where('original_recharge_operation_id', $validated['originalRechargeOperationId'])
                ->whereNotIn('status', ['FAILED', 'CANCELLED'])
                ->lockForUpdate()
                ->first();

            if ($existingReversal) {
                throw new CardManagementError('REVERSAL_ALREADY_EXISTS', 409, 'A reversal already exists for this recharge.');
            }

            return $this->operations->create([
                'card_id' => $card->id,
                'original_recharge_operation_id' => $validated['originalRechargeOperationId'],
                'idempotency_key' => $validated['idempotencyKey'],
                'expected_uid' => $uid,
                'card_number' => $card->card_number,
                'workstation_id' => $workstationId,
                'amount_minor_units' => $validated['amountMinorUnits'],
                'original_recharge_completed_at' => $originalRecharge->completed_at ?? now(),
                'terminal_number_hex' => $validated['terminalNumberHex'],
                'terminal_transaction_sequence' => $validated['terminalTransactionSequence'],
                'key_profile_version' => $card->key_profile_version ?? 'hitee-lab-v1',
            ]);
        });

        return ResponseEnvelope::success(
            ['operation' => $this->operations->toPublicArray($operation)],
            201
        );
    }

    public function getReversal(string $operationId)
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

        // Reversal uses the same key preparation as the original recharge
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

        DB::table('cm_wallet_recharge_reversal_operations')->where('id', $operation->id)->update([
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
        $envelopeRow = DB::table('cm_wallet_recharge_reversal_key_envelopes')
            ->where('operation_id', $operation->id)
            ->first();

        if (!$envelopeRow) {
            throw new CardManagementError('KEY_ENVELOPE_NOT_READY', 409, 'Key envelope not yet prepared.');
        }

        return ResponseEnvelope::success([
            'keyEnvelope' => [
                'id' => $envelopeRow->id,
                'operationId' => $operation->id,
                'envelope' => json_decode($envelopeRow->envelope, true),
            ],
            'operation' => $this->operations->toPublicArray($operation),
        ]);
    }

    public function acknowledgeEnvelope(Request $request, string $operationId)
    {
        $operation = $this->operations->findOrFail($operationId);

        DB::table('cm_wallet_recharge_reversal_key_envelopes')
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
            $request->input('failureMessage', 'Reversal failed.'),
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
