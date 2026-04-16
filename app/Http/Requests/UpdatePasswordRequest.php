<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rules\Password;

/**
 * Validation for updating user password
 */
class UpdatePasswordRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
