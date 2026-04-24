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
        $credentials = $request->validate([
            'phone_number' => ['required', 'string'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::user();

            // 1. Check if email is verified
            if (!$user->hasVerifiedEmail()) {
                Auth::logout();
                return redirect()->route('verification.notice');
            }

            // 2. Check for active status (1)
            if ($user->status != \App\Models\User::STATUS_ACTIVE) {
                Auth::logout();
                
                if ($user->status == \App\Models\User::STATUS_PENDING) {
                    return redirect()->route('login')->with('warning', 'Your account is pending admin approval.');
                }
                
                throw ValidationException::withMessages(['phone_number' => 'Your account is deactivated.']);
            }

            $request->session()->regenerate();
            logActivity('login', 'User logged in');

            return redirect()->intended('dashboard');
        }

        throw ValidationException::withMessages([
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
