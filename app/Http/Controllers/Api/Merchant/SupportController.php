<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\SupportRequest;
use Illuminate\Http\Request;

/**
 * @group MerchantApi 
 * @subgroup Support
 */
class SupportController extends Controller
{
    /**
     * Get Support Info
     */
    public function index()
    {
        $data = [
            'support_email' => Setting::get('support_email', 'support@hitee.ai'),
            'support_phone' => Setting::get('support_phone', '+977-1-1234567'),
            'faqs_url'      => Setting::get('merchant_faqs_url', 'https://hitee.ai/merchant/faqs'),
        ];

        return apiResponse(true, 'Support information fetched successfully', $data);
    }

    /**
     * Submit Support Request
     */
    public function store(Request $request)
    {
        $request->validate([
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string|min:10',
        ]);

        $user = $request->user();

        $support = SupportRequest::create([
            'user_id' => $user->id,
            'subject' => $request->subject ?? 'Merchant Inquiry',
            'message' => $request->message,
            'status'  => 'open',
        ]);

        logActivity('merchant_support_request', 'Merchant submitted a support request via API', ['id' => $support->id], $user->id);

        return apiResponse(true, 'Your support request has been submitted successfully.');
    }
}
