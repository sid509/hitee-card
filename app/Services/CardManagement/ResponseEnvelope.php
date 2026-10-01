<?php

namespace App\Services\CardManagement;

/**
 * Builds the canonical Card Management API response envelope.
 *
 * Success: { "success": true, "data": { ... } }
 * Error:   { "success": false, "error": { "code", "message", "details" } }
 */
final class ResponseEnvelope
{
    public static function success(array $data, int $status = 200): \Illuminate\Http\JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }

    public static function created(array $data): \Illuminate\Http\JsonResponse
    {
        return self::success($data, 201);
    }

    public static function error(string $code, string $message, int $httpStatus, ?array $details = null): \Illuminate\Http\JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];
        if ($details !== null) {
            $error['details'] = $details;
        }

        return response()->json(['success' => false, 'error' => $error], $httpStatus);
    }

    public static function fromError(CardManagementError $e): \Illuminate\Http\JsonResponse
    {
        return self::error($e->errorCode, $e->getMessage(), $e->httpStatus, $e->details);
    }
}
