<?php

namespace App\Http\Requests;

/**
 * Validation for creating a new user
 */
class StoreUserRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone_number' => 'required|string|max:20|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'roles' => 'nullable|array'
        ];
    }
}
