<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\SupportRequest;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    /**
     * GET /api/support
     *
     * Returns support contact information.
     */
    public function index()
    {
        $data = [
            'support_email' => Setting::get('support_email', 'support@hitee.ai'),
            'support_phone' => Setting::get('support_phone', '+977-1-1234567'),
        ];

        return apiResponse(true, 'Support information fetched successfully', $data);
    }

    /**
     * POST /api/support
     *
     * Submits a support request (contact form).
     *
     * @bodyParam subject string (optional) The subject of the support request. Defaults to "General Inquiry".
     * @bodyParam message string (required) The message content.
     */
    public function store(Request $request)
    {
        $request->validate([
            'subject' => [
                'nullable',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if ($value && strlen($value) <= 5 && str_word_count($value) < 3) {
                        $fail('The ' . $attribute . ' must be more than 5 characters or at least 3 words.');
                    }
                },
            ],
            'message' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (strlen($value) <= 5 && str_word_count($value) < 3) {
                        $fail('The ' . $attribute . ' must be more than 5 characters or at least 3 words.');
                    }
                },
            ],
        ]);

        $user = $request->user();

        $support = SupportRequest::create([
            'user_id' => $user->id,
            'subject' => $request->subject ?? 'General Inquiry',
            'message' => $request->message,
            'status'  => 'open',
        ]);

        logActivity('support_request', 'User submitted a support request via API', ['id' => $support->id], $user->id);

        return apiResponse(true, 'Your support request has been submitted successfully.');
    }
}
