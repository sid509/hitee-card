<?php

use App\Http\Controllers\CardManagement\AuditController;
use App\Http\Controllers\CardManagement\CardsController;
use App\Http\Controllers\CardManagement\DebitController;
use App\Http\Controllers\CardManagement\InitializationController;
use App\Http\Controllers\CardManagement\IssuanceController;
use App\Http\Controllers\CardManagement\ReceiptsController;
use App\Http\Controllers\CardManagement\RecoveryController;
use App\Http\Controllers\CardManagement\ReplacementController;
use App\Http\Controllers\CardManagement\ReversalController;
use App\Http\Controllers\CardManagement\SettlementController;
use App\Http\Controllers\CardManagement\ValidatorController;
use App\Http\Controllers\CardManagement\WalletController;
use App\Services\CardManagement\CardManagementError;
use App\Services\CardManagement\ResponseEnvelope;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Card Management API Routes
|--------------------------------------------------------------------------
| Implements PRODUCTION_BACKEND_API_REQUIREMENTS.md
| All routes under /api/v1/ use the spec's response envelope:
|   { "success": true, "data": { ... } }
| The existing mobile API under /api/ is unaffected.
*/

Route::prefix('v1')->group(function () {

    // Shared middleware applied to all Card Management API routes
    Route::middleware([
        \App\Http\Middleware\CardManagement\CardManagementContext::class,
        \App\Http\Middleware\CardManagement\CardManagementAuth::class,
        \App\Http\Middleware\CardManagement\IdempotencyKey::class,
        \App\Http\Middleware\CardManagement\CardManagementAudit::class,
    ])->group(function () {

        // ── Cards (§5.1) ──────────────────────────────────────────────
        Route::prefix('cards')->group(function () {
            Route::post('check', [CardsController::class, 'check']);
            Route::post('register', [CardsController::class, 'register']);
            Route::get('{uid}', [CardsController::class, 'get'])
                ->where('card_uid', '[0-9A-Fa-f]{8,32}');
            Route::post('{uid}/assign-lab-profile', [CardsController::class, 'assignLabProfile'])
                ->where('card_uid', '[0-9A-Fa-f]{8,32}');
            Route::get('{uid}/initialization-operation', [CardsController::class, 'getInitializationOperation'])
                ->where('card_uid', '[0-9A-Fa-f]{8,32}');
            Route::post('{uid}/initialization-operations', [CardsController::class, 'createInitializationOperation'])
                ->where('card_uid', '[0-9A-Fa-f]{8,32}');
        });

        // ── Initialization Operations (§5.2) ───────────────────────────
        Route::prefix('initialization-operations')->group(function () {
            Route::get('configuration', [InitializationController::class, 'getConfiguration']);
            Route::get('{operationId}', [InitializationController::class, 'getOperation']);
            Route::post('{operationId}/prepare-keys', [InitializationController::class, 'prepareKeys']);
            Route::get('{operationId}/key-envelope', [InitializationController::class, 'getEnvelope']);
            Route::post('{operationId}/authorize-physical-write', [InitializationController::class, 'authorizePhysicalWrite']);
            Route::post('{operationId}/key-envelope/acknowledge', [InitializationController::class, 'acknowledge']);
            Route::post('{operationId}/checkpoints', [InitializationController::class, 'checkpoint']);
            Route::post('{operationId}/complete', [InitializationController::class, 'complete']);
            Route::post('{operationId}/fail', [InitializationController::class, 'fail']);
            Route::post('{operationId}/cancel', [InitializationController::class, 'cancel']);
        });

        // ── Wallet / Recharge (§5.3) ───────────────────────────────────
        Route::prefix('wallet')->group(function () {
            Route::get('configuration', [WalletController::class, 'getConfiguration']);
            Route::get('cards/{uid}/active', [WalletController::class, 'getActiveByCard'])
                ->where('card_uid', '[0-9A-Fa-f]{8,32}');
            Route::get('cards/{uid}/recharges', [WalletController::class, 'getHistoryByCard'])
                ->where('card_uid', '[0-9A-Fa-f]{8,32}');
            Route::post('cards/{uid}/recharges', [WalletController::class, 'createRecharge'])
                ->where('card_uid', '[0-9A-Fa-f]{8,32}');
            Route::get('recharges/{operationId}', [WalletController::class, 'getRecharge']);
            Route::post('recharges/{operationId}/prepare-keys', [WalletController::class, 'prepareKeys']);
            Route::post('recharges/{operationId}/prepare-reconciliation-keys', [WalletController::class, 'prepareReconciliationKeys']);
            Route::post('recharges/{operationId}/authorize', [WalletController::class, 'authorize']);
            Route::get('recharges/{operationId}/key-envelope', [WalletController::class, 'getEnvelope']);
            Route::post('recharges/{operationId}/key-envelope/acknowledge', [WalletController::class, 'acknowledgeEnvelope']);
            Route::post('recharges/{operationId}/attempt-credit', [WalletController::class, 'attemptCredit']);
            Route::post('recharges/{operationId}/checkpoints', [WalletController::class, 'checkpoint']);
            Route::post('recharges/{operationId}/complete', [WalletController::class, 'complete']);
            Route::post('recharges/{operationId}/reconcile', [WalletController::class, 'reconcile']);
            Route::post('recharges/{operationId}/fail', [WalletController::class, 'fail']);
            Route::post('recharges/{operationId}/cancel', [WalletController::class, 'cancel']);
        });

        // ── Debit (§5.4) ───────────────────────────────────────────────
        Route::prefix('debit')->group(function () {
            Route::get('configuration', [DebitController::class, 'getConfiguration']);
            Route::get('cards/{uid}/active', [DebitController::class, 'getActiveByCard'])
                ->where('card_uid', '[0-9A-Fa-f]{8,32}');
            Route::get('cards/{uid}/debits', [DebitController::class, 'getHistoryByCard'])
                ->where('card_uid', '[0-9A-Fa-f]{8,32}');
            Route::post('cards/{uid}/debits', [DebitController::class, 'createDebit'])
                ->where('card_uid', '[0-9A-Fa-f]{8,32}');
            Route::get('operations/{operationId}', [DebitController::class, 'getDebit']);
            Route::post('operations/{operationId}/prepare-keys', [DebitController::class, 'prepareKeys']);
            Route::post('operations/{operationId}/authorize', [DebitController::class, 'authorize']);
            Route::get('operations/{operationId}/key-envelope', [DebitController::class, 'getEnvelope']);
            Route::post('operations/{operationId}/key-envelope/acknowledge', [DebitController::class, 'acknowledgeEnvelope']);
            Route::post('operations/{operationId}/attempt-debit', [DebitController::class, 'attemptDebit']);
            Route::post('operations/{operationId}/checkpoints', [DebitController::class, 'checkpoint']);
            Route::post('operations/{operationId}/complete', [DebitController::class, 'complete']);
            Route::post('operations/{operationId}/fail', [DebitController::class, 'fail']);
            Route::post('operations/{operationId}/cancel', [DebitController::class, 'cancel']);
        });

        // ── Reversal (§5.5) ────────────────────────────────────────────
        Route::prefix('reversal')->group(function () {
            Route::get('configuration', [ReversalController::class, 'getConfiguration']);
            Route::get('cards/{uid}/active', [ReversalController::class, 'getActiveByCard'])
                ->where('card_uid', '[0-9A-Fa-f]{8,32}');
            Route::get('cards/{uid}/reversals', [ReversalController::class, 'getHistoryByCard'])
                ->where('card_uid', '[0-9A-Fa-f]{8,32}');
            Route::post('cards/{uid}/reversals', [ReversalController::class, 'createReversal'])
                ->where('card_uid', '[0-9A-Fa-f]{8,32}');
            Route::get('operations/{operationId}', [ReversalController::class, 'getReversal']);
            Route::post('operations/{operationId}/prepare-keys', [ReversalController::class, 'prepareKeys']);
            Route::post('operations/{operationId}/authorize', [ReversalController::class, 'authorize']);
            Route::get('operations/{operationId}/key-envelope', [ReversalController::class, 'getEnvelope']);
            Route::post('operations/{operationId}/key-envelope/acknowledge', [ReversalController::class, 'acknowledgeEnvelope']);
            Route::post('operations/{operationId}/attempt-debit', [ReversalController::class, 'attemptDebit']);
            Route::post('operations/{operationId}/checkpoints', [ReversalController::class, 'checkpoint']);
            Route::post('operations/{operationId}/complete', [ReversalController::class, 'complete']);
            Route::post('operations/{operationId}/fail', [ReversalController::class, 'fail']);
            Route::post('operations/{operationId}/cancel', [ReversalController::class, 'cancel']);
        });

        // ── Issuance (§5.6) ────────────────────────────────────────────
        Route::prefix('issuance')->group(function () {
            Route::get('configuration', [IssuanceController::class, 'getConfiguration']);
            Route::post('customers', [IssuanceController::class, 'createCustomer']);
            Route::get('customers', [IssuanceController::class, 'searchCustomers']);
            Route::post('cards/{uid}/operations', [IssuanceController::class, 'createIssuance'])
                ->where('card_uid', '[0-9A-Fa-f]{8,32}');
            Route::get('cards/{uid}/active', [IssuanceController::class, 'getActive'])
                ->where('card_uid', '[0-9A-Fa-f]{8,32}');
            Route::get('cards/{uid}/history', [IssuanceController::class, 'getHistory'])
                ->where('card_uid', '[0-9A-Fa-f]{8,32}');
            Route::get('cards/{uid}/issuance-details', [IssuanceController::class, 'getIssuanceDetails'])
                ->where('card_uid', '[0-9A-Fa-f]{8,32}');
            Route::post('operations/{operationId}/prepare-keys', [IssuanceController::class, 'prepareKeys']);
            Route::post('operations/{operationId}/authorize', [IssuanceController::class, 'authorize']);
            Route::get('operations/{operationId}/key-envelope', [IssuanceController::class, 'getEnvelope']);
            Route::post('operations/{operationId}/key-envelope/acknowledge', [IssuanceController::class, 'acknowledgeEnvelope']);
            Route::post('operations/{operationId}/checkpoints', [IssuanceController::class, 'checkpoint']);
            Route::post('operations/{operationId}/complete', [IssuanceController::class, 'complete']);
            Route::post('operations/{operationId}/cancel', [IssuanceController::class, 'cancel']);
            Route::post('operations/{operationId}/fail', [IssuanceController::class, 'fail']);
            Route::post('cards/{uid}/lifecycle', [IssuanceController::class, 'lifecycleAction'])
                ->where('card_uid', '[0-9A-Fa-f]{8,32}');
            Route::get('cards/{uid}/lifecycle', [IssuanceController::class, 'lifecycleHistory'])
                ->where('card_uid', '[0-9A-Fa-f]{8,32}');
        });

        // ── Replacement (§5.7) ─────────────────────────────────────────
        Route::prefix('replacement')->group(function () {
            Route::get('configuration', [ReplacementController::class, 'getConfiguration']);
            Route::post('cards/{uid}/operations', [ReplacementController::class, 'createReplacement'])
                ->where('card_uid', '[0-9A-Fa-f]{8,32}');
            Route::get('cards/{uid}/active', [ReplacementController::class, 'getActiveByCard'])
                ->where('card_uid', '[0-9A-Fa-f]{8,32}');
            Route::get('cards/{uid}/history', [ReplacementController::class, 'getHistoryByCard'])
                ->where('card_uid', '[0-9A-Fa-f]{8,32}');
            Route::get('operations/{operationId}', [ReplacementController::class, 'getOperation']);
            Route::post('operations/{operationId}/balance-evidence', [ReplacementController::class, 'recordBalanceEvidence']);
            Route::post('operations/{operationId}/prepare-keys', [ReplacementController::class, 'prepareKeys']);
            Route::post('operations/{operationId}/authorize', [ReplacementController::class, 'authorize']);
            Route::get('operations/{operationId}/key-envelope', [ReplacementController::class, 'getEnvelope']);
            Route::post('operations/{operationId}/key-envelope/acknowledge', [ReplacementController::class, 'acknowledgeEnvelope']);
            Route::post('operations/{operationId}/checkpoints', [ReplacementController::class, 'checkpoint']);
            Route::post('operations/{operationId}/complete', [ReplacementController::class, 'complete']);
            Route::post('operations/{operationId}/cancel', [ReplacementController::class, 'cancel']);
            Route::post('operations/{operationId}/fail', [ReplacementController::class, 'fail']);
            Route::post('operations/{operationId}/prepare-transfer', [ReplacementController::class, 'prepareTransfer']);
            Route::post('operations/{operationId}/reconcile-transfer', [ReplacementController::class, 'reconcileTransfer']);
        });

        // ── Recovery (§5.8) ────────────────────────────────────────────
        Route::get('recovery/items', [RecoveryController::class, 'listItems']);
        Route::get('recovery/items/{operationType}/{operationId}', [RecoveryController::class, 'getItem'])
            ->where('operationType', '[A-Z]+')
            ->where('operationId', '[0-9a-fA-F\-]{36}');

        // ── Audit (§5.9) ───────────────────────────────────────────────
        Route::get('audit/events', [AuditController::class, 'listEvents']);

        // ── Receipts (§5.10) ───────────────────────────────────────────
        Route::get('receipts/{operationType}/{operationId}', [ReceiptsController::class, 'getReceipt']);

        // ── Settlement Engine (Phase 61) ──────────────────────────────
        Route::prefix('settlement')->group(function () {
            Route::post('run', [SettlementController::class, 'run']);
            Route::get('batches', [SettlementController::class, 'listBatches']);
            Route::get('batches/{batchId}', [SettlementController::class, 'getBatch'])
                ->where('batchId', '[0-9a-fA-F\-]{36}');
            Route::post('batches/{batchId}/approve', [SettlementController::class, 'approve'])
                ->where('batchId', '[0-9a-fA-F\-]{36}');
            Route::post('batches/{batchId}/pay', [SettlementController::class, 'markPaid'])
                ->where('batchId', '[0-9a-fA-F\-]{36}');
            Route::post('batches/{batchId}/close', [SettlementController::class, 'close'])
                ->where('batchId', '[0-9a-fA-F\-]{36}');
            Route::get('batches/{batchId}/payout-file', [SettlementController::class, 'payoutFile'])
                ->where('batchId', '[0-9a-fA-F\-]{36}');
        });
    });

    // ── Validator Device API (Phases 7, 13-14, 44-46, 48) ──────────────
    // Device-facing routes authenticate with the per-device Bearer token
    // issued at devices/register — NOT the workstation token. They live
    // outside the workstation-auth group because a single Authorization
    // header cannot satisfy both token types.
    // devices/register is public by design; re-registration is guarded
    // inside the controller (current device token or provisioning secret).
    Route::middleware([
        \App\Http\Middleware\CardManagement\CardManagementContext::class,
        \App\Http\Middleware\CardManagement\IdempotencyKey::class,
        \App\Http\Middleware\CardManagement\CardManagementAudit::class,
    ])->group(function () {
        Route::post('devices/register', [ValidatorController::class, 'register']);
        Route::middleware([\App\Http\Middleware\CardManagement\ValidatorDeviceAuth::class])->group(function () {
            Route::post('devices/heartbeat', [ValidatorController::class, 'heartbeat']);
            Route::post('devices/{deviceId}/trips/sync', [ValidatorController::class, 'syncTrips'])
                ->where('deviceId', '[a-zA-Z0-9_-]+');
            Route::get('blocklist/delta', [ValidatorController::class, 'blocklistDelta']);
            Route::get('cards/{uid}/blocklist-status', [ValidatorController::class, 'blocklistStatus'])
                ->where('uid', '[0-9A-Fa-f]{8,32}');
        });
    });
});

// Global Card Management error handler
// Catches CardManagementError and returns the canonical error envelope.
Route::bind('cmOperationId', function (string $value) {
    return $value;
});
