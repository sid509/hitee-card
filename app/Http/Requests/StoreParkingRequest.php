<?php

namespace App\Http\Requests;

/**
 * Validation for creating a new parking
 */
class StoreParkingRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'merchant_id' => 'nullable|exists:users,id',
            'status' => 'required|in:opened,closed',
        ];
    }
}
