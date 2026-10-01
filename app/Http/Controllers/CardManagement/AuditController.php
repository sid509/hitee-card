<?php

namespace App\Http\Controllers\CardManagement;

use App\Models\AuditEvent;
use App\Services\CardManagement\ResponseEnvelope;
use Illuminate\Http\Request;

/**
 * Audit controller.
 * Returns append-only audit events per PRODUCTION_BACKEND_API_REQUIREMENTS §5.9.
 */
final class AuditController extends BaseCardManagementController
{
    public function listEvents(Request $request)
    {
        $query = AuditEvent::query();

        if ($operationType = $request->query('operationType')) {
            $query->where('operation_type', $operationType);
        }
        if ($cardReference = $request->query('cardReference')) {
            $query->where('card_uid', $cardReference);
        }
        if ($operatorId = $request->query('operatorId')) {
            $query->where('operator_id', $operatorId);
        }
        if ($action = $request->query('action')) {
            $query->where('action', $action);
        }

        $limit = min((int) $request->query('limit', 50), 200);
        $events = $query->orderBy('recorded_at', 'desc')->limit($limit)->get();

        $eventsArray = $events->map(fn ($e) => [
            'id' => $e->id,
            'requestId' => $e->request_id,
            'action' => $e->action,
            'entityType' => $e->entity_type,
            'entityId' => $e->entity_id,
            'operationType' => $e->operation_type,
            'operationId' => $e->operation_id,
            'cardId' => $e->card_id,
            'cardNumber' => $e->card_number,
            'cardUid' => $e->card_uid,
            'workstationId' => $e->workstation_id,
            'operatorId' => $e->operator_id,
            'httpMethod' => $e->http_method,
            'httpPath' => $e->http_path,
            'result' => $e->result,
            'operationState' => $e->operation_state,
            'recordedAt' => $e->recorded_at?->toIso8601ZuluString(),
        ])->toArray();

        return ResponseEnvelope::success(['events' => $eventsArray, 'nextCursor' => null]);
    }
}
