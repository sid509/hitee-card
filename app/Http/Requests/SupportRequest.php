<?php

namespace App\Http\Requests;

/**
 * Validation for support message
 */
class SupportRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'message' => 'required|string|max:5000',
        ];
    }
}
