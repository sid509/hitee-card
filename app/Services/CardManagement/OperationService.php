<?php

namespace App\Services\CardManagement;

use App\Services\CardManagement\CardManagementError;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generic operation service for Card Management API operation modules.
 *
 * Implements the common CRUD lifecycle pattern used by Wallet, Debit,
 * Reversal, Issuance, and Replacement operation controllers.
 *
 * Each module has its own tables following the naming convention:
 *   cm_{prefix}_operations         — main operation record
 *   cm_{prefix}_checkpoints        — step checkpoints
 *   cm_{prefix}_key_envelopes      — key envelope delivery tracking
 *
 * This service provides the shared create/read/checkpoint/complete/fail/cancel
 * logic so controllers stay thin and consistent.
 */
final class OperationService
{
    public function __construct(
        private readonly string $table,
        private readonly string $checkpointTable,
        private readonly string $envelopeTable,
    ) {}

    /**
     * Create a new operation record.
     */
    public function create(array $data): object
    {
        $id = Str::uuid()->toString();

        // Idempotency check
        if (isset($data['idempotency_key'])) {
            $existing = DB::table($this->table)
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        $insertData = array_merge(['id' => $id], $data, [
            'lock_version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Set default status if not provided
        if (!isset($insertData['status'])) {
            $insertData['status'] = 'KEYS_PENDING';
        }

        // Only add started_at if the table has that column
        // (issuance and replacement tables use created_at instead)
        if ($this->hasColumn('started_at')) {
            $insertData['started_at'] = now();
        }

        DB::table($this->table)->insert($insertData);

        return DB::table($this->table)->where('id', $id)->first();
    }

    /**
     * Find an operation by ID or throw.
     */
    public function findOrFail(string $operationId): object
    {
        $operation = DB::table($this->table)->where('id', $operationId)->first();

        if (!$operation) {
            throw new CardManagementError('OPERATION_NOT_FOUND', 404, 'Operation not found.');
        }

        return $operation;
    }

    /**
     * Find operations by card ID.
     * Handles both `card_id` (wallet/debit/issuance) and `new_card_id` (replacement) columns.
     */
    public function findByCard(string $cardId): array
    {
        $query = DB::table($this->table);
        if ($this->hasColumn('new_card_id')) {
            $query->where('new_card_id', $cardId);
        } else {
            $query->where('card_id', $cardId);
        }
        return $query->orderBy('created_at', 'desc')->get()->all();
    }

    /**
     * Find the active (non-terminal) operation for a card.
     * Handles both `card_id` (wallet/debit/issuance) and `new_card_id` (replacement) columns.
     */
    public function findActiveByCard(string $cardId): ?object
    {
        $query = DB::table($this->table);
        if ($this->hasColumn('new_card_id')) {
            $query->where('new_card_id', $cardId);
        } else {
            $query->where('card_id', $cardId);
        }
        return $query->whereNotIn('status', ['COMPLETED', 'FAILED', 'CANCELLED'])
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Update operation status with optimistic locking.
     */
    public function updateStatus(string $operationId, string $status, int $expectedVersion, ?string $step = null): object
    {
        $updated = DB::table($this->table)
            ->where('id', $operationId)
            ->where('lock_version', $expectedVersion)
            ->update([
                'status' => $status,
                'last_successful_step' => $step,
                'lock_version' => $expectedVersion + 1,
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            $current = $this->findOrFail($operationId);
            throw new CardManagementError(
                'VERSION_CONFLICT',
                409,
                'Operation version mismatch.',
                ['expected' => $expectedVersion, 'actual' => $current->lock_version]
            );
        }

        return $this->findOrFail($operationId);
    }

    /**
     * Record a checkpoint for an operation step.
     */
    public function recordCheckpoint(string $operationId, string $step, ?array $metadata = null): void
    {
        $existing = DB::table($this->checkpointTable)
            ->where('operation_id', $operationId)
            ->where('step', $step)
            ->first();

        if ($existing) {
            return;
        }

        $sequence = DB::table($this->checkpointTable)
            ->where('operation_id', $operationId)
            ->max('sequence_no') ?? 0;

        DB::table($this->checkpointTable)->insert([
            'id' => Str::uuid()->toString(),
            'operation_id' => $operationId,
            'step' => $step,
            'sequence_no' => $sequence + 1,
            'result_metadata' => $metadata ? json_encode($metadata) : null,
            'created_at' => now(),
        ]);
    }

    /**
     * Get all checkpoints for an operation.
     */
    public function getCheckpoints(string $operationId): array
    {
        return DB::table($this->checkpointTable)
            ->where('operation_id', $operationId)
            ->orderBy('sequence_no')
            ->get()
            ->all();
    }

    /**
     * Mark operation as completed.
     */
    public function complete(string $operationId, int $expectedVersion, ?array $extra = []): object
    {
        $updateData = array_merge([
            'status' => 'COMPLETED',
            'lock_version' => $expectedVersion + 1,
            'completed_at' => now(),
            'updated_at' => now(),
        ], $extra);

        // Only add failed_at if the table has that column
        if (!$this->hasColumn('completed_at')) {
            unset($updateData['completed_at']);
        }

        // Guard the source state in the same atomic UPDATE: a terminal
        // operation (COMPLETED/FAILED/CANCELLED) can never be completed again.
        $updated = DB::table($this->table)
            ->where('id', $operationId)
            ->where('lock_version', $expectedVersion)
            ->whereNotIn('status', ['COMPLETED', 'FAILED', 'CANCELLED'])
            ->update($updateData);

        if ($updated === 0) {
            // Distinguish "wrong version" from "already in a terminal state" —
            // the latter is a state violation, not an optimistic-lock conflict.
            $current = DB::table($this->table)->where('id', $operationId)->first();
            if ($current && (int) $current->lock_version === $expectedVersion) {
                throw new CardManagementError(
                    'INVALID_STATE_TRANSITION',
                    409,
                    "Cannot complete operation in status {$current->status}."
                );
            }
            throw new CardManagementError('VERSION_CONFLICT', 409, 'Operation version mismatch.');
        }

        return $this->findOrFail($operationId);
    }

    /**
     * Mark operation as failed.
     */
    public function fail(string $operationId, int $expectedVersion, string $code, string $message): object
    {
        $updateData = [
            'status' => 'FAILED',
            'lock_version' => $expectedVersion + 1,
            'failure_code' => $code,
            'failure_message' => $message,
            'updated_at' => now(),
        ];

        // Only add failed_at if the table has that column
        if ($this->hasColumn('failed_at')) {
            $updateData['failed_at'] = now();
        }

        $updated = DB::table($this->table)
            ->where('id', $operationId)
            ->where('lock_version', $expectedVersion)
            ->update($updateData);

        if ($updated === 0) {
            throw new CardManagementError('VERSION_CONFLICT', 409, 'Operation version mismatch.');
        }

        return $this->findOrFail($operationId);
    }

    /**
     * Cancel an operation (only if not yet completed/failed).
     */
    public function cancel(string $operationId, int $expectedVersion): object
    {
        $operation = $this->findOrFail($operationId);

        if (in_array($operation->status, ['COMPLETED', 'FAILED'])) {
            throw new CardManagementError('INVALID_STATE_TRANSITION', 409, 'Cannot cancel a terminal operation.');
        }

        return $this->updateStatus($operationId, 'CANCELLED', $expectedVersion, 'CANCELLED');
    }

    /**
     * Convert an operation record to a public array.
     */
    public function toPublicArray(object $operation): array
    {
        return [
            'id' => $operation->id,
            'cardId' => $operation->card_id ?? null,
            'status' => $operation->status,
            'lockVersion' => $operation->lock_version,
            'lastSuccessfulStep' => $operation->last_successful_step,
            'amountMinorUnits' => $operation->amount_minor_units ?? null,
            'startedAt' => isset($operation->started_at)
                ? \Carbon\Carbon::parse($operation->started_at)->toIso8601ZuluString()
                : ($operation->created_at ?? null),
            'completedAt' => isset($operation->completed_at)
                ? \Carbon\Carbon::parse($operation->completed_at)->toIso8601ZuluString()
                : null,
            'failureCode' => $operation->failure_code ?? null,
            'failureMessage' => $operation->failure_message ?? null,
        ];
    }

    /**
     * Check if the table has a given column.
     * Caches the result for the request lifetime.
     */
    private function hasColumn(string $column): bool
    {
        static $cache = [];
        $key = $this->table . '.' . $column;
        if (isset($cache[$key])) return $cache[$key];

        $schema = DB::getSchemaBuilder();
        $columns = $schema->getColumnListing($this->table);
        $cache[$key] = in_array($column, $columns, true);

        return $cache[$key];
    }
}
