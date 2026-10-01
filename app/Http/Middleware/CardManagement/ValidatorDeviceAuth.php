<?php

namespace App\Http\Middleware\CardManagement;

use App\Models\ValidatorDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticate a validator device by its Bearer token.
 *
 * Device tokens are issued at registration (POST /api/v1/devices/register)
 * and stored sha256-hashed on validator_devices.api_token. The token is
 * provisioned onto the validator hardware at device setup; the Flutter
 * client already sends it on every platform call.
 *
 * On success the resolved device is attached as the `validator_device`
 * request attribute.
 */
class ValidatorDeviceAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (! $token) {
            return $this->unauthorized('Device API token required.');
        }

        $hashed = hash('sha256', $token);

        $device = ValidatorDevice::where('api_token', $hashed)
            ->where('status', 'ACTIVE')
            ->first();

        if (! $device || ! hash_equals($device->api_token, $hashed)) {
            return $this->unauthorized('Invalid device credentials.');
        }

        $request->attributes->set('validator_device', $device);

        return $next($request);
    }

    private function unauthorized(string $message): Response
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => 'DEVICE_UNAUTHORIZED', 'message' => $message],
        ], 401);
    }
}
