<?php

namespace App\Http\Requests;

/**
 * Validation for updating an existing parking
 */
class UpdateParkingRequest extends BaseRequest
{
    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'status' => 'required|in:opened,closed',
        ];

        if (auth()->user()->hasRole('super-admin')) {
            $rules['merchant_id'] = 'nullable|exists:users,id';
        }

        return $rules;
    }
}
