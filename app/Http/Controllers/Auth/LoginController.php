<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'phone_number' => ['required', 'string'],
            'password' => ['required'],
        ]);

        $user = \App\Models\User::where('phone_number', $request->phone_number)->first();

        // 1. First, check if the user exists and the password is correct
        if ($user && \Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
            
            // 2. Check email verification status
            if (!$user->hasVerifiedEmail()) {
                Auth::logout();
                return redirect()->route('login')->with([
                    'warning' => 'Please verify your email address before logging in.',
                    'needs_verification' => true
                ])->withInput($request->only('phone_number', 'remember'));
            }

            // 3. Check account activation status (1 = Active)
            if ($user->status != \App\Models\User::STATUS_ACTIVE) {
                Auth::logout();
                
                if ($user->status == \App\Models\User::STATUS_PENDING) {
                    return redirect()->route('login')->with('info', 'Your account is verified but awaiting administrative approval. You will be notified once activated.');
                }
                
                return redirect()->route('login')->with('error', 'Your account has been deactivated. Please contact support.');
            }

            // 4. Everything is correct, proceed to login
            Auth::login($user, $request->boolean('remember'));
            $request->session()->regenerate();
            logActivity('login', 'User logged in');

            return redirect()->intended('dashboard');
        }

        // Authentication failed (Wrong phone number or password)
        throw \Illuminate\Validation\ValidationException::withMessages([
            'phone_number' => __('auth.failed'),
        ]);
    }


    public function logout(Request $request)
    {
        logActivity('logout', 'User logged out');
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
