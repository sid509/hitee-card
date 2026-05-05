<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Models\FcmToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * @group MerchantApi 
 * @subgroup Auth
 */
class AuthController extends Controller
{
    /**
     * Merchant Login
     */
    public function login(LoginRequest $request)
    {
        $user = User::where('phone_number', $request->phone_number)->first();

        if ($user && Hash::check($request->password, $user->password)) {
            if (!$user->hasRole('merchant')) {
                return apiResponse(false, 'Unauthorized. Access restricted to merchants.', '', 403);
            }

            if ($user->status != User::STATUS_ACTIVE) {
                if ($user->status == User::STATUS_PENDING) {
                    return apiResponse(false, __('messages.account_pending'), ['pending_approval' => true], 403);
                }
                return apiResponse(false, __('messages.account_deactivated'), '', 403);
            }

            return $this->respondWithToken($user, __('messages.login_success'), $request->fcm_token);
        }

        return apiResponse(false, __('messages.invalid_credentials'), '', 401);
    }

    /**
     * Merchant Logout
     */
    public function logout(Request $request)
    {
        if ($request->fcm_token) {
            FcmToken::where('token', $request->fcm_token)->delete();
        }

        logActivity('merchant_logout', 'Merchant logged out via API');
        $request->user()->tokens()->delete();

        return apiResponse(true, __('messages.logout_success'));
    }

    private function respondWithToken(User $user, string $message, ?string $fcmToken = null)
    {
        if ($fcmToken) {
            FcmToken::updateOrCreate(
                ['token' => $fcmToken],
                ['user_id' => $user->id]
            );
        }

        $user->tokens()->delete();

        $accessToken = $user->createToken('merchant_access_token', ['access'])->plainTextToken;
        $refreshToken = $user->createToken('merchant_refresh_token', ['refresh'])->plainTextToken;

        $user->load(['roles']);

        return apiResponse(true, $message, [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'avatar_url' => $user->avatar_url,
                'status' => $user->status,
                'merchant_balance' => (float)$user->merchantBalance(),
                'roles' => $user->roles->pluck('name'),
            ]
        ]);
    }
}
