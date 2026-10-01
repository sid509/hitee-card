<?php

namespace App\Http\Middleware\CardManagement;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Extracts x-workstation-id, x-operator-id, x-request-id headers
 * and makes them available to controllers via the request attributes
 * and the CardManagementContext singleton.
 */
final class CardManagementContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $workstationId = $request->header('x-workstation-id');
        $operatorId = $request->header('x-operator-id');
        $requestId = $request->header('x-request-id') ?? \Illuminate\Support\Str::uuid()->toString();

        // Validator device endpoints use Bearer token auth, not workstation ID
        $isValidatorRoute = $request->is('api/v1/devices/*') ||
                            $request->is('api/v1/blocklist/*') ||
                            $request->is('api/v1/cards/*/blocklist-status');

        if (!$workstationId && !$isValidatorRoute) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'MISSING_WORKSTATION_ID', 'message' => 'x-workstation-id header is required.'],
            ], 400);
        }

        // For validator routes, use device ID or 'validator' as workstation ID
        $workstationId = $workstationId ?? ($request->input('deviceId') ?? 'validator');

        $request->attributes->set('cm_workstation_id', $workstationId);
        $request->attributes->set('cm_operator_id', $operatorId);
        $request->attributes->set('cm_request_id', $requestId);

        $response = $next($request);
        $response->headers->set('x-request-id', $requestId);

        return $response;
    }
}
