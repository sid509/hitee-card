<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * Validation for updating user profile
 */
class UpdateProfileRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users')->ignore(auth()->id()),
            ],
            'phone_number' => [
                'required',
                'string',
                'max:20',
                Rule::unique('users')->ignore(auth()->id()),
            ],
        ];
    }
}
