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
            'password' => Hash::make($request->password),
            'status' => 'active',
        ]);

        $customerRole = Role::where('slug', 'customers')->first();
        if ($customerRole) {
            $user->roles()->attach($customerRole);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        logActivity('registration', 'New user registered via API', [], $user->id);

        return apiResponse(true, 'User registered successfully', [
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * Login user and create token
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
        $token = $user->createToken('auth_token')->plainTextToken;

        logActivity('login', 'User logged in via API', [], $user->id);

        return apiResponse(true, 'Login successful', [
            'user' => $user,
            'access_token' => $token,
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

            $token = $user->createToken('auth_token')->plainTextToken;

            logActivity('login', 'User logged in via social login (' . $provider . ')', [], $user->id);

            return apiResponse(true, 'Social login successful', [
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
            ]);

        } catch (\Exception $e) {
            return apiResponse(false, 'Social login failed: ' . $e->getMessage(), '', 400);
        }
    }

    /**
     * Logout user (Revoke token)
     * 
     * @authenticated
     */
    public function logout(Request $request)
    {
        logActivity('logout', 'User logged out via API');
        $request->user()->currentAccessToken()->delete();

        return apiResponse(true, 'Logged out successfully');
    }
}
