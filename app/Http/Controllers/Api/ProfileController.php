<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\Setting;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

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
        
        // Fetch last trip detail
        $lastRide = $user->rides()
            ->with(['tapIn:id,resolved_location_name,created_at', 'tapOut:id,resolved_location_name,created_at', 'merchant:id,name'])
            ->latest()
            ->first();

        // Calculate dynamic stats
        $totalTrips = $user->rides()->where('status', 'completed')->count();
        $totalParking = $user->balanceOuts()->where('type', 'parking')->count();
        $totalBalance = $user->balance(); // Current usable balance

        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'avatar_url' => $user->avatar_url,
            'preferred_language' => $user->preferred_language,
            'notification_enabled' => (bool) $user->notification_enabled,
            'status' => $user->status,
            'roles' => $user->roles->pluck('name'),
            'stats' => [
                'current_balance' => (float) $totalBalance,
                'total_trips'     => $totalTrips,
                'total_parking'   => $totalParking,
                'lifetime_spent'  => (float) $user->balanceOuts()->sum('amount'),
            ],
            'active_card' => $user->activeCard ? [
                'id' => $user->activeCard->id,
                'card_number' => $user->activeCard->card_number,
                'status' => $user->activeCard->status,
                'balance' => $user->activeCard->balance(),
            ] : null,
            'last_trip' => $lastRide ? [
                'id' => $lastRide->id,
                'status' => $lastRide->status,
                'fare_pts' => (float) $lastRide->fare_amount,
                'merchant' => $lastRide->merchant?->name,
                'tap_in' => $lastRide->tapIn ? [
                    'location' => $lastRide->tapIn->resolved_location_name,
                    'time' => $lastRide->tapIn->created_at?->toISOString(),
                ] : null,
                'tap_out' => $lastRide->tapOut ? [
                    'location' => $lastRide->tapOut->resolved_location_name,
                    'time' => $lastRide->tapOut->created_at?->toISOString(),
                ] : null,
                'date' => $lastRide->created_at?->toISOString(),
            ] : null,
            'settings' => [
                'terms_url'     => Setting::get('terms_url', 'https://hitee.ai/terms'),
                'policy_url'    => Setting::get('policy_url', 'https://hitee.ai/privacy'),
                'support_email' => Setting::get('support_email', 'support@hitee.ai'),
                'support_phone' => Setting::get('support_phone', '+977-1-1234567'),
            ]
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

        // Phone update not allowed as per requirement
        $user->update($request->only('name', 'email'));

        logActivity('profile_update', 'User updated profile details via API', [], $user->id);

        return apiResponse(true, 'Profile updated successfully', [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
        ]);
    }

    /**
     * Update profile image.
     */
    public function updateImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $user = $request->user();

        // Use standard HasMedia trait methods
        $user->clearMediaCollection('avatar');
        $user->addMedia($request->file('image'), 'avatar');

        return apiResponse(true, 'Profile image updated successfully', [
            'avatar_url' => $user->avatar_url,
        ]);
    }

    /**
     * Update password.
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return apiResponse(false, 'Current password does not match', '', 400);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        logActivity('password_update', 'User updated password via API', [], $user->id);

        return apiResponse(true, 'Password updated successfully');
    }

    /**
     * GET /api/profile/rides
     * 
     * Returns a paginated list of completed rides for the authenticated user.
     */
    public function rides(Request $request)
    {
        $perPage = (int) $request->get('perPage', 5);
        
        $rides = $request->user()->rides()
            ->where('status', 'completed')
            ->with(['tapIn', 'tapOut', 'merchant:id,name'])
            ->latest()
            ->paginate($perPage);

        $mapped = collect($rides->items())->map(fn($ride) => [
            'id'         => $ride->id,
            'fare_pts'   => (float) $ride->fare_amount,
            'merchant'   => $ride->merchant?->name,
            'tap_in'     => $ride->tapIn ? [
                'location' => $ride->tapIn->resolved_location_name,
                'time'     => $ride->tapIn->created_at?->toISOString(),
            ] : null,
            'tap_out'    => $ride->tapOut ? [
                'location' => $ride->tapOut->resolved_location_name,
                'time'     => $ride->tapOut->created_at?->toISOString(),
            ] : null,
            'date'       => $ride->created_at?->toISOString(),
        ]);

        return apiResponse(true, 'Rides fetched successfully', $mapped, 200, [], [
            'total'        => $rides->total(),
            'per_page'     => $rides->perPage(),
            'current_page' => $rides->currentPage(),
            'last_page'    => $rides->lastPage(),
        ]);
    }

    /**
     * GET /api/profile/taps
     * 
     * Returns a paginated list of raw taps for the authenticated user.
     */
    public function taps(Request $request)
    {
        $perPage = (int) $request->get('perPage', 5);

        $taps = $request->user()->taps()
            ->with(['card:id,card_number', 'merchant:id,name'])
            ->latest()
            ->paginate($perPage);

        $mapped = collect($taps->items())->map(fn($tap) => [
            'id'            => $tap->id,
            'type'          => $tap->type, // 'in' | 'out'
            'location'      => $tap->resolved_location_name,
            'card_number'   => $tap->card?->card_number,
            'merchant_name' => $tap->merchant?->name,
            'date'          => $tap->created_at?->toISOString(),
        ]);

        return apiResponse(true, 'Tap logs fetched successfully', $mapped, 200, [], [
            'total'        => $taps->total(),
            'per_page'     => $taps->perPage(),
            'current_page' => $taps->currentPage(),
            'last_page'    => (int) ceil($total / $perPage),
        ]);
        }

        /**
        * POST /api/profile/language
        * 
        * Update user's preferred language.
        */
        public function updateLanguage(Request $request)
        {
        $request->validate([
            'language' => 'required|string|in:en,ne',
        ]);

        $request->user()->update(['preferred_language' => $request->language]);

        return apiResponse(true, 'Preferred language updated successfully');
        }

        /**
        * POST /api/profile/notifications
        * 
        * Update user's notification preference.
        */
        public function updateNotification(Request $request)
        {
        $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $request->user()->update(['notification_enabled' => $request->enabled]);

        return apiResponse(true, 'Notification preference updated successfully');
        }
        }

