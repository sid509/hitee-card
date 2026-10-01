<?php

namespace App\Http\Controllers\CardManagement;

use App\Services\CardManagement\ResponseEnvelope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Recovery controller.
 * Returns a read-only queue of ambiguous/recovery-required operations
 * across all operation types per PRODUCTION_BACKEND_API_REQUIREMENTS §5.8.
 *
 * Scans all operation tables for operations in AMBIGUOUS, RECOVERY_REQUIRED,
 * or physical_state_uncertain=true statuses.
 */
final class RecoveryController extends BaseCardManagementController
{
    private const RECOVERY_TABLES = [
        'INITIALIZATION' => 'cm_card_initialization_operations',
        'RECHARGE' => 'cm_wallet_recharge_operations',
        'DEBIT' => 'cm_wallet_debit_operations',
        'REVERSAL' => 'cm_wallet_recharge_reversal_operations',
        'ISSUANCE' => 'cm_card_issuance_operations',
        'REPLACEMENT' => 'cm_card_replacement_operations',
    ];

    private const RECOVERY_STATUSES = ['AMBIGUOUS', 'RECOVERY_REQUIRED', 'CREDIT_PENDING_VERIFICATION', 'DEBIT_PENDING_VERIFICATION'];

    public function listItems(Request $request)
    {
        $items = [];
        $statusFilter = $request->input('status');
        $typeFilter = $request->input('type');

        foreach (self::RECOVERY_TABLES as $type => $table) {
            if ($typeFilter && $typeFilter !== $type) continue;

            $query = DB::table($table);

            if ($statusFilter) {
                $query->where('status', $statusFilter);
            } else {
                $query->where(function ($q) {
                    $q->whereIn('status', self::RECOVERY_STATUSES)
                      ->orWhere('physical_state_uncertain', true);
                });
            }

            $operations = $query->orderBy('created_at', 'desc')->limit(100)->get();

            foreach ($operations as $op) {
                $items[] = [
                    'operationType' => $type,
                    'operationId' => $op->id,
                    'status' => $op->status,
                    'physicalStateUncertain' => (bool) ($op->physical_state_uncertain ?? false),
                    'cardId' => $op->card_id ?? $op->new_card_id ?? null,
                    'workstationId' => $op->workstation_id ?? null,
                    'failureCode' => $op->failure_code ?? null,
                    'failureMessage' => $op->failure_message ?? null,
                    'lastSuccessfulStep' => $op->last_successful_step ?? null,
                    'createdAt' => $op->created_at ?? null,
                ];
            }
        }

        // Sort by created_at desc
        usort($items, fn ($a, $b) => strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? ''));

        return ResponseEnvelope::success([
            'items' => $items,
            'count' => count($items),
        ]);
    }

    public function getItem(string $operationType, string $operationId)
    {
        if (!isset(self::RECOVERY_TABLES[$operationType])) {
            return ResponseEnvelope::success(['item' => null]);
        }

        $table = self::RECOVERY_TABLES[$operationType];
        $operation = DB::table($table)->where('id', $operationId)->first();

        if (!$operation) {
            return ResponseEnvelope::success(['item' => null]);
        }

        // Get checkpoints
        $checkpointTable = str_replace('_operations', '_checkpoints', $table);
        $checkpoints = DB::table($checkpointTable)
            ->where('operation_id', $operationId)
            ->orderBy('sequence_no')
            ->get();

        return ResponseEnvelope::success([
            'item' => [
                'operationType' => $operationType,
                'operationId' => $operation->id,
                'status' => $operation->status,
                'physicalStateUncertain' => (bool) ($operation->physical_state_uncertain ?? false),
                'cardId' => $operation->card_id ?? $operation->new_card_id ?? null,
                'workstationId' => $operation->workstation_id ?? null,
                'failureCode' => $operation->failure_code ?? null,
                'failureMessage' => $operation->failure_message ?? null,
                'lastSuccessfulStep' => $operation->last_successful_step ?? null,
                'lockVersion' => $operation->lock_version ?? 0,
                'createdAt' => $operation->created_at ?? null,
                'checkpoints' => $checkpoints->map(fn ($c) => [
                    'step' => $c->step,
                    'sequenceNo' => $c->sequence_no,
                    'metadata' => $c->result_metadata ? json_decode($c->result_metadata, true) : null,
                    'createdAt' => $c->created_at,
                ])->toArray(),
            ],
        ]);
    }
}
