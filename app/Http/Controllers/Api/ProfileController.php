<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\UpdateProfileRequest;

class ProfileController extends Controller
{
    /**
     * Get the authenticated user's profile.
     */
    public function show(Request $request)
    {
        $user = $request->user();
        
        // Ensure we are using an access token, not a refresh token
        if (!$request->user()->currentAccessToken()->can('access')) {
            return apiResponse(false, 'Invalid token type for this action', '', 403);
        }

        $user->load(['roles', 'activeCard']);
        
        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'status' => $user->status,
            'balance' => $user->balance(),
            'roles' => $user->roles->pluck('name'),
            'active_card' => $user->activeCard ? [
                'id' => $user->activeCard->id,
                'card_number' => $user->activeCard->card_number,
                'status' => $user->activeCard->status,
                'balance' => $user->activeCard->balance(),
            ] : null,
        ];

        return apiResponse(true, 'Profile fetched successfully', $data);
    }

    /**
     * Update the authenticated user's profile.
     */
    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();
        
        // Ensure we are using an access token, not a refresh token
        if (!$request->user()->currentAccessToken()->can('access')) {
            return apiResponse(false, 'Invalid token type for this action', '', 403);
        }

        $user->update($request->only('name', 'email', 'phone_number'));

        logActivity('profile_update', 'User updated profile details via API', [], $user->id);

        return apiResponse(true, 'Profile updated successfully', [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
        ]);
    }
}
