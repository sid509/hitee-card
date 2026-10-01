<?php

namespace App\Http\Middleware\CardManagement;

use App\Models\CardManagement\AuditEvent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records append-only audit events after request completion.
 * Never logs request bodies, keys, tokens, PINs, or APDUs.
 */
final class CardManagementAudit
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only audit mutating requests
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $response;
        }

        try {
            $this->recordEvent($request, $response);
        } catch (\Throwable $e) {
            // Audit failures must never break the response
            report($e);
        }

        return $response;
    }

    private function recordEvent(Request $request, Response $response): void
    {
        $path = $request->path();
        $action = $this->deriveAction($request->method(), $path);

        // Don't audit health checks or audit reads
        if ($action === null) {
            return;
        }

        $operationId = $request->route('operationId');
        $cardUid = $request->route('uid');

        $operationState = null;
        $body = json_decode($response->getContent(), true);
        if (isset($body['data']['operation'])) {
            $op = $body['data']['operation'];
            $operationState = ['status' => $op['status'] ?? null, 'version' => $op['version'] ?? null];
        }

        AuditEvent::create([
            'request_id' => $request->attributes->get('cm_request_id'),
            'action' => $action,
            'entity_type' => null,
            'entity_id' => $operationId,
            'operation_type' => $this->deriveOperationType($path),
            'operation_id' => $operationId,
            'card_id' => null,
            'card_number' => null,
            'card_uid' => $cardUid,
            'workstation_id' => $request->attributes->get('cm_workstation_id'),
            'operator_id' => $request->attributes->get('cm_operator_id'),
            'http_method' => $request->method(),
            'http_path' => '/' . $path,
            'result' => $response->isSuccessful() ? 'SUCCESS' : 'FAILURE',
            'operation_state' => $operationState,
        ]);
    }

    private function deriveAction(string $method, string $path): ?string
    {
        if (preg_match('#/complete$#', $path)) return 'OPERATION_COMPLETE';
        if (preg_match('#/fail$#', $path)) return 'OPERATION_FAIL';
        if (preg_match('#/cancel$#', $path)) return 'OPERATION_CANCEL';
        if (preg_match('#/prepare-keys$#', $path)) return 'PREPARE_KEYS';
        if (preg_match('#/authorize#', $path)) return 'AUTHORIZE';
        if (preg_match('#/checkpoints$#', $path)) return 'CHECKPOINT';
        if (preg_match('#/key-envelope/acknowledge$#', $path)) return 'KEY_ENVELOPE_ACK';
        if (preg_match('#/key-envelope$#', $path)) return 'KEY_ENVELOPE_DELIVER';
        if (preg_match('#/attempt-credit$#', $path)) return 'ATTEMPT_CREDIT';
        if (preg_match('#/attempt-debit$#', $path)) return 'ATTEMPT_DEBIT';
        if (preg_match('#/reconcile#', $path)) return 'RECONCILE';
        if (preg_match('#/balance-evidence$#', $path)) return 'BALANCE_EVIDENCE';
        if (preg_match('#/lifecycle$#', $path)) return 'LIFECYCLE_ACTION';
        if (preg_match('#/cards/check$#', $path)) return 'CARD_CHECK';
        if (preg_match('#/cards/register$#', $path)) return 'CARD_REGISTER';
        if (preg_match('#/assign-lab-profile$#', $path)) return 'ASSIGN_LAB_PROFILE';
        if (preg_match('#/customers$#', $path) && $method === 'POST') return 'CUSTOMER_CREATE';
        if (preg_match('#/operations$#', $path) && $method === 'POST') return 'OPERATION_CREATE';
        if (preg_match('#/debits$#', $path) && $method === 'POST') return 'OPERATION_CREATE';
        if (preg_match('#/recharges$#', $path) && $method === 'POST') return 'OPERATION_CREATE';
        if (preg_match('#/reversals$#', $path) && $method === 'POST') return 'OPERATION_CREATE';
        return null;
    }

    private function deriveOperationType(string $path): ?string
    {
        if (str_contains($path, 'initialization')) return 'INITIALIZATION';
        if (str_contains($path, 'wallet/recharges')) return 'RECHARGE';
        if (str_contains($path, 'debit')) return 'DEBIT';
        if (str_contains($path, 'reversal')) return 'REVERSAL';
        if (str_contains($path, 'issuance')) return 'ISSUANCE';
        if (str_contains($path, 'replacement')) return 'REPLACEMENT';
        if (str_contains($path, 'cards')) return 'CARD';
        return null;
    }
}
