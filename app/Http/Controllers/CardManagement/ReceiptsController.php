<?php

namespace App\Http\Controllers\CardManagement;

use App\Services\CardManagement\CardManagementError;
use App\Services\CardManagement\ResponseEnvelope;
use Illuminate\Support\Facades\DB;

/**
 * Receipts controller.
 * Generates receipts only for SUCCEEDED operations per
 * PRODUCTION_BACKEND_API_REQUIREMENTS §5.10.
 *
 * Looks up the operation by type+id, verifies it's in a terminal
 * state, and builds the receipt payload with card, operator, and
 * financial details.
 */
final class ReceiptsController extends BaseCardManagementController
{
    private const OPERATION_TABLES = [
        'INITIALIZATION' => 'cm_card_initialization_operations',
        'RECHARGE' => 'cm_wallet_recharge_operations',
        'DEBIT' => 'cm_wallet_debit_operations',
        'REVERSAL' => 'cm_wallet_recharge_reversal_operations',
        'ISSUANCE' => 'cm_card_issuance_operations',
        'REPLACEMENT' => 'cm_card_replacement_operations',
    ];

    public function getReceipt(string $operationType, string $operationId)
    {
        $validTypes = array_keys(self::OPERATION_TABLES);
        if (!in_array($operationType, $validTypes, true)) {
            throw new CardManagementError('INVALID_OPERATION_TYPE', 400, 'Invalid operation type.');
        }

        $table = self::OPERATION_TABLES[$operationType];
        $operation = DB::table($table)->where('id', $operationId)->first();

        if (!$operation) {
            throw new CardManagementError('OPERATION_NOT_FOUND', 404, 'Operation not found.');
        }

        // Only generate receipts for completed operations
        if ($operation->status !== 'COMPLETED') {
            throw new CardManagementError(
                'INVALID_STATE_TRANSITION',
                409,
                "Receipt only available for COMPLETED operations. Current status: {$operation->status}"
            );
        }

        // Look up card info
        $cardId = $operation->card_id ?? $operation->new_card_id ?? null;
        $card = $cardId ? DB::table('cm_cards')->where('id', $cardId)->first() : null;

        // Build receipt payload
        $receipt = [
            'receiptNumber' => 'RCP-' . substr($operationId, 0, 8),
            'operationType' => $operationType,
            'operationId' => $operationId,
            'operationStatus' => $operation->status,
            'workstationId' => $operation->workstation_id ?? null,
            'cardUid' => $card?->uid,
            'cardNumber' => $card?->card_number ?? $operation->card_number ?? null,
            'amountMinorUnits' => $operation->amount_minor_units ?? null,
            'balanceBefore' => $operation->balance_before ?? null,
            'balanceAfter' => $operation->balance_after ?? null,
            'transactionDateTime' => $operation->transaction_datetime ?? null,
            'completedAt' => $operation->completed_at ?? null,
            'generatedAt' => now()->toIso8601ZuluString(),
        ];

        // Add type-specific fields
        if ($operationType === 'ISSUANCE') {
            $receipt['customerName'] = $this->getCustomerName($operation->customer_id ?? null);
            $receipt['cardCategory'] = $operation->card_category ?? null;
            $receipt['expiryDate'] = $operation->expiry_date ?? null;
        }

        if ($operationType === 'REPLACEMENT') {
            $receipt['oldCardReference'] = $operation->old_card_reference ?? null;
            $receipt['reason'] = $operation->reason ?? null;
            $receipt['transferStatus'] = $operation->transfer_status ?? null;
        }

        if ($operationType === 'REVERSAL') {
            $receipt['originalRechargeOperationId'] = $operation->original_recharge_operation_id ?? null;
        }

        return ResponseEnvelope::success(['receipt' => $receipt]);
    }

    private function getCustomerName(?string $customerId): ?string
    {
        if (!$customerId) return null;
        $customer = DB::table('cm_customers')->where('id', $customerId)->first();
        return $customer?->full_name;
    }
}
