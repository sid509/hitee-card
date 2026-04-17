<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UpdatePasswordRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * Show the user profile.
     */
    public function show()
    {
        return view('modules.profile.show', ['user' => auth()->user()]);
    }

    /**
     * Update the user profile details.
     * 
     * Uses UpdateProfileRequest for validation.
     */
    public function update(UpdateProfileRequest $request)
    {
        $user = auth()->user();
        $user->update($request->only('name', 'email'));

        logActivity('profile_update', 'User updated profile details');

        return back()->with('success', 'Profile updated successfully.');
    }

    /**
     * Update the user password.
     * 
     * Uses UpdatePasswordRequest for validation.
     */
    public function password(UpdatePasswordRequest $request)
    {
        auth()->user()->update([
            'password' => Hash::make($request->password),
        ]);

        logActivity('password_change', 'User changed their password');

        return back()->with('success', 'Password changed successfully.');
    }
}
