<?php

namespace App\Http\Controllers\CardManagement;

use App\Http\Controllers\Controller;
use App\Services\CardManagement\CardManagementError;
use App\Services\CardManagement\ResponseEnvelope;
use Illuminate\Http\Request;

/**
 * Base controller for Card Management API modules.
 * Provides shared helpers for optimistic locking, idempotency, and
 * the canonical response envelope.
 */
abstract class BaseCardManagementController extends Controller
{
    /**
     * Get the workstation ID from the request context.
     */
    protected function workstationId(Request $request): string
    {
        return $request->attributes->get('cm_workstation_id');
    }

    /**
     * Get the operator ID from the request context.
     */
    protected function operatorId(Request $request): ?string
    {
        return $request->attributes->get('cm_operator_id');
    }

    /**
     * Get the request ID from the request context.
     */
    protected function requestId(Request $request): string
    {
        return $request->attributes->get('cm_request_id');
    }

    /**
     * Get the idempotency key from the header, if present.
     */
    protected function idempotencyKey(Request $request): ?string
    {
        return $request->header('idempotency-key');
    }

    /**
     * Verify expectedVersion matches the current lock_version.
     * Throws VersionConflictError on mismatch.
     */
    protected function assertVersion(int $expected, int $actual): void
    {
        if ($expected !== $actual) {
            throw new CardManagementError(
                'VERSION_CONFLICT',
                409,
                'Operation version mismatch.',
                ['expected' => $expected, 'actual' => $actual]
            );
        }
    }

    /**
     * Verify a confirmation phrase by SHA-256 digest comparison.
     */
    protected function verifyConfirmationPhrase(string $phrase, ?string $expectedDigest): void
    {
        if (!$expectedDigest) {
            throw new CardManagementError(
                'PHYSICAL_WRITE_NOT_AUTHORIZED',
                403,
                'No confirmation phrase expected for this operation.'
            );
        }

        $actualDigest = hash('sha256', $phrase);
        if (!hash_equals($expectedDigest, $actualDigest)) {
            throw new CardManagementError(
                'INVALID_CONFIRMATION_PHRASE',
                403,
                'Confirmation phrase does not match.'
            );
        }
    }

    /**
     * Check UID allowlist (if configured).
     */
    protected function assertUidAllowed(string $uid, array $allowlist): void
    {
        if (empty($allowlist)) {
            return;
        }
        if (!in_array(strtoupper($uid), array_map('strtoupper', $allowlist), true)) {
            throw new CardManagementError(
                'UID_ALLOWLIST_VIOLATION',
                403,
                'Card UID not in allowlist.'
            );
        }
    }
}
