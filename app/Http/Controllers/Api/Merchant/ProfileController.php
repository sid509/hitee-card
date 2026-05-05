<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\Setting;
use Illuminate\Support\Facades\Hash;

/**
 * @group MerchantApi 
 * @subgroup Profile
 */
class ProfileController extends Controller
{
    /**
     * Get Merchant Profile
     */
    public function show(Request $request)
    {
        $user = $request->user();
        $user->load(['roles']);
        
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
                'merchant_balance' => (float) $user->merchantBalance(),
                'total_buses'     => $user->buses()->count(),
                'total_parkings'   => $user->parkings()->count(),
                'total_revenue'    => (float) $user->merchantIncomes()->sum('amount'),
            ],
            'settings' => [
                'terms_url'     => Setting::get('terms_url', 'https://hitee.ai/terms'),
                'policy_url'    => Setting::get('policy_url', 'https://hitee.ai/privacy'),
                'support_email' => Setting::get('support_email', 'support@hitee.ai'),
                'support_phone' => Setting::get('support_phone', '+977-1-1234567'),
            ]
        ];

        return apiResponse(true, __('messages.profile_fetched'), $data);
    }

    /**
     * Update Merchant Profile
     */
    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $user->update($request->only('name', 'email'));

        logActivity('merchant_profile_update', 'Merchant updated profile via API', [], $user->id);

        return apiResponse(true, __('messages.profile_updated'), [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
        ]);
    }

    /**
     * Update Profile Image
     */
    public function updateImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $user = $request->user();
        $user->clearMediaCollection('avatar');
        $user->addMedia($request->file('image'), 'avatar');

        return apiResponse(true, 'Profile image updated successfully', [
            'avatar_url' => $user->avatar_url,
        ]);
    }

    /**
     * Update Password
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return apiResponse(false, __('messages.current_password_mismatch'), '', 400);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        logActivity('merchant_password_update', 'Merchant updated password via API', [], $user->id);

        return apiResponse(true, __('messages.password_updated'));
    }

    /**
     * Update Language
     */
    public function updateLanguage(Request $request)
    {
        $request->validate(['language' => 'required|string|in:en,ne']);
        $request->user()->update(['preferred_language' => $request->language]);
        return apiResponse(true, __('messages.language_updated'));
    }

    /**
     * Update Notification Toggle
     */
    public function updateNotification(Request $request)
    {
        $request->validate(['enabled' => 'required|boolean']);
        $request->user()->update(['notification_enabled' => $request->enabled]);
        return apiResponse(true, __('messages.notifications_updated'));
    }
}
