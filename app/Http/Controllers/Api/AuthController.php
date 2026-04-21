<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/**
 * @group Authentication
 * 
 * APIs for managing authentication
 */
class AuthController extends Controller
{
    /**
     * Register a new user
     * 
     * @bodyParam name string required The name of the user. Example: John Doe
     * @bodyParam email string required The email of the user. Example: john@example.com
     * @bodyParam password string required The password of the user. Example: password123
     * @bodyParam password_confirmation string required The confirmation of the password. Example: password123
     */
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'password' => Hash::make($request->password),
            'status' => 'active',
        ]);

        $customerRole = Role::where('slug', 'customers')->first();
        if ($customerRole) {
            $user->roles()->attach($customerRole);
        }

        if ($request->card_number) {
            $card = \App\Models\Card::where('card_number', $request->card_number)->first();
            if ($card) {
                // If the card is already linked to someone else, we might want to handle it.
                // For now, following the instruction to "link to the correct card".
                $card->update([
                    'user_id' => $user->id,
                    'is_currently_active' => true
                ]);
            }
        }

        logActivity('registration', 'New user registered via API', [], $user->id);

        return apiResponse(true, 'User registered successfully. Please log in.', [], 201);
    }

    /**
     * Login user and create tokens
     * 
     * @bodyParam email string required The email of the user. Example: admin@example.com
     * @bodyParam password string required The password of the user. Example: password
     */
    public function login(LoginRequest $request)
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            return apiResponse(false, 'Invalid login credentials', '', 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        
        // Revoke old tokens
        $user->tokens()->delete();

        $accessToken = $user->createToken('access_token', ['access'])->plainTextToken;
        $refreshToken = $user->createToken('refresh_token', ['refresh'])->plainTextToken;

        logActivity('login', 'User logged in via API', [], $user->id);

        return apiResponse(true, 'Login successful', [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email
            ]
        ]);
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
            return apiResponse(false, 'Invalid token type for refresh', '', 403);
        }

        // Revoke all current tokens for security on refresh
        $user->tokens()->delete();

        $accessToken = $user->createToken('access_token', ['access'])->plainTextToken;
        $refreshToken = $user->createToken('refresh_token', ['refresh'])->plainTextToken;

        return apiResponse(true, 'Tokens refreshed successfully', [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Social Login via Provider Token
     * 
     * This endpoint handles login/registration via Google or Facebook access tokens.
     * 
     * @bodyParam provider string required The social provider (google or facebook). Example: google
     * @bodyParam access_token string required The access token received from the provider.
     */
    public function socialLogin(Request $request)
    {
        $request->validate([
            'provider' => 'required|in:google,facebook',
            'access_token' => 'required|string',
        ]);

        $provider = $request->provider;
        $token = $request->access_token;

        try {
            $socialUser = Socialite::driver($provider)->userFromToken($token);
            
            $user = User::where('email', $socialUser->getEmail())->first();

            if (!$user) {
                $user = User::create([
                    'name' => $socialUser->getName() ?? $socialUser->getNickname(),
                    'email' => $socialUser->getEmail(),
                    'password' => Hash::make(Str::random(24)),
                    'status' => 'active',
                ]);

                $customerRole = Role::where('slug', 'customers')->first();
                if ($customerRole) {
                    $user->roles()->attach($customerRole);
                }
            }

            // Revoke old tokens
            $user->tokens()->delete();

            $accessToken = $user->createToken('access_token', ['access'])->plainTextToken;
            $refreshToken = $user->createToken('refresh_token', ['refresh'])->plainTextToken;

            logActivity('login', 'User logged in via social login (' . $provider . ')', [], $user->id);

            return apiResponse(true, 'Social login successful', [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email
                ]
            ]);

        } catch (\Exception $e) {
            return apiResponse(false, 'Social login failed: ' . $e->getMessage(), '', 400);
        }
    }

    /**
     * Logout user (Revoke tokens)
     * 
     * @authenticated
     */
    public function logout(Request $request)
    {
        logActivity('logout', 'User logged out via API');
        $request->user()->tokens()->delete();

        return apiResponse(true, 'Logged out successfully');
    }
}
