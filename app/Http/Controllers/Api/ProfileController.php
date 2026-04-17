<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

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

        $user->load(['roles']);
        
        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
            'balance' => $user->balance(),
            'roles' => $user->roles->pluck('name'),
        ];

        return apiResponse(true, 'Profile fetched successfully', $data);
    }
}
