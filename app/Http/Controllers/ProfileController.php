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
        $user->update($request->only('name', 'email', 'phone_number', 'is_tourist'));

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
            $file = $request->file('avatar');
            $extension = $file->getClientOriginalExtension();
            $customName = 'avatar_profile_' . now()->format('Ymd_His') . '.' . $extension;

            // Clear old avatar collection
            $user->clearMediaCollection('avatar');
            
            // Add new media
            $user->addMedia($file, 'avatar', $customName);

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

    /**
     * Submit KYC for verification.
     */
    public function submitKyc(Request $request)
    {
        $request->validate([
            'full_name' => 'required|string|max:255',
            'id_type' => 'required|string',
            'id_number' => 'required|string|max:255',
            'kyc_type' => 'required|in:student,old_age,tourist,standard',
        ]);

        $user = auth()->user();

        // Check if there is already a pending or approved request
        if ($user->kycVerification()->whereIn('status', ['requested', 'approved'])->exists()) {
            return back()->with('error', 'You already have a pending or approved KYC request.');
        }

        \App\Models\KycVerification::create([
            'user_id' => $user->id,
            'full_name' => $request->full_name,
            'id_type' => $request->id_type,
            'id_number' => $request->id_number,
            'kyc_type' => $request->kyc_type,
            'status' => 'requested',
        ]);

        logActivity('kyc_submission', 'User submitted KYC for verification', [
            'kyc_type' => $request->kyc_type
        ]);

        return back()->with('success', 'KYC submitted successfully. Our team will verify it shortly.');
    }
}
