<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * Validation for updating an existing user
 */
class UpdateUserRequest extends BaseRequest
{
    public function rules(): array
    {
        $userId = $this->route('user') ? $this->route('user')->id : $this->user;
        
        return [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($userId),
            ],
            'phone_number' => [
                'required',
                'string',
                'max:20',
                Rule::unique('users')->ignore($userId),
            ],
            'password' => 'nullable|string|min:8|confirmed',
            'roles' => 'nullable|array',
            'is_tourist' => 'nullable|boolean'
        ];
    }
}
