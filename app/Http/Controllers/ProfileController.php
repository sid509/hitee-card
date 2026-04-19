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
     * Update the user avatar.
     */
    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $user = auth()->user();

        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar && \Storage::disk('public')->exists($user->avatar)) {
                \Storage::disk('public')->delete($user->avatar);
            }

            $path = $request->file('avatar')->store('avatars', 'public');
            $user->update(['avatar' => $path]);

            logActivity('avatar_update', 'User updated profile avatar');

            return back()->with('success', 'Avatar updated successfully.');
        }

        return back()->with('error', 'No avatar file selected.');
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
