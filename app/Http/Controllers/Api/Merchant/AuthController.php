<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Models\FcmToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Sanctum\PersonalAccessToken;

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
            if (!$user->hasRole('merchant', 'staff')) {
                return apiResponse(false, 'Unauthorized. Access restricted to merchants and staff.', '', 403);
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
     * Biometric Login via Access Token
     * 
     * @bodyParam access_token string required The access token.
     * @bodyParam fcm_token string (optional) FCM token for notifications.
     */
    public function biometricLogin(Request $request)
    {
        $request->validate([
            'access_token' => 'required|string',
            'fcm_token' => 'nullable|string',
        ]);

        $token = PersonalAccessToken::findToken($request->access_token);

        if (!$token || !$token->can('access')) {
            return apiResponse(false, __('messages.invalid_token'), '', 403);
        }

        $user = $token->tokenable;

        if (!$user->hasRole('merchant', 'staff')) {
            return apiResponse(false, 'Unauthorized. Access restricted to merchants and staff.', '', 403);
        }

        // Check account activation status
        if ($user->status != User::STATUS_ACTIVE) {
            if ($user->status == User::STATUS_PENDING) {
                return apiResponse(false, __('messages.account_pending'), ['pending_approval' => true], 403);
            }
            return apiResponse(false, __('messages.account_deactivated'), '', 403);
        }

        logActivity('merchant_login', 'Merchant logged in via biometric (access token)', [], $user->id);

        return $this->respondWithToken($user, __('messages.login_success'), $request->fcm_token);
    }

    /**
     * Refresh tokens
     * 
     * @authenticated
     */
    public function refresh(Request $request)
    {
        $user = $request->user();
        $currentToken = $user->currentAccessToken();

        // Check if the current token has the refresh ability
        if (!$currentToken->can('refresh')) {
            return apiResponse(false, __('messages.invalid_refresh_token'), '', 403);
        }

        // Revoke all current tokens for security on refresh
        $user->tokens()->delete();

        $accessToken = $user->createToken('merchant_access_token', ['access'])->plainTextToken;
        $refreshToken = $user->createToken('merchant_refresh_token', ['refresh'])->plainTextToken;

        return apiResponse(true, __('messages.tokens_refreshed'), [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
        ]);
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

    /**
     * Social Login via Provider Token
     * 
     * Handles login via social providers (google, facebook).
     * 
     * @bodyParam provider string required The provider name (google, facebook).
     * @bodyParam access_token string required The token from provider.
     */
    public function socialLogin(Request $request)
    {
        $request->validate([
            'provider' => 'required|in:google,facebook',
            'access_token' => 'required|string',
            'fcm_token' => 'nullable|string',
        ]);

        $provider = $request->provider;
        $token = $request->access_token;

        try {
            $socialUser = Socialite::driver($provider)->userFromToken($token);
            $user = User::where('email', $socialUser->getEmail())->first();

            if (!$user) {
                return apiResponse(false, 'Merchant account not found. Please contact administrator.', '', 404);
            }

            if (!$user->hasRole('merchant', 'staff')) {
                return apiResponse(false, 'Unauthorized. Access restricted to merchants and staff.', '', 403);
            }

            if ($user->status != User::STATUS_ACTIVE) {
                return apiResponse(false, __('messages.account_deactivated'), '', 403);
            }

            logActivity('merchant_login', 'Merchant logged in via social login (' . $provider . ')', [], $user->id);

            return $this->respondWithToken($user, __('messages.social_login_success'), $request->fcm_token);

        } catch (\Exception $e) {
            return apiResponse(false, __('messages.social_login_failed') . ': ' . $e->getMessage(), '', 400);
        }
    }

    /**
     * Forgot Password
     * 
     * Send a password reset link to the merchant's email.
     * 
     * @bodyParam email string required The merchant's email.
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        // Optional: Check if user exists and has merchant role before sending
        $user = User::where('email', $request->email)->first();
        if ($user && !$user->hasRole('merchant', 'staff')) {
            return apiResponse(false, 'Unauthorized. This email is not associated with a merchant or staff account.', '', 403);
        }

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? apiResponse(true, __($status))
            : apiResponse(false, __($status), '', 400);
    }

    /**
     * Reset Password
     * 
     * Reset the merchant's password using the token received in email.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? apiResponse(true, __($status))
            : apiResponse(false, __($status), '', 400);
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
