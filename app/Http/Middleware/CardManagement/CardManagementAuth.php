<?php

namespace App\Http\Middleware\CardManagement;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional bearer-token authentication for the Card Management API.
 * Only enforced when CM_AUTH_REQUIRED=true.
 */
final class CardManagementAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('card_management.auth.required', false)) {
            return $next($request);
        }

        $expectedToken = config('card_management.auth.workstation_api_token');
        if (!$expectedToken) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'AUTH_NOT_CONFIGURED', 'message' => 'Authentication is required but no token is configured.'],
            ], 500);
        }

        $header = $request->header('authorization', '');
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Bearer token required.'],
            ], 401);
        }

        if (!hash_equals($expectedToken, $m[1])) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Invalid API token.'],
            ], 401);
        }

        return $next($request);
    }
}
