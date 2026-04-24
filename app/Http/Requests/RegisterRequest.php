<?php

namespace App\Http\Requests;

class RegisterRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone_number' => 'required|string|regex:/^9\d{9}$/|unique:users',
            'card_number' => 'nullable|string|exists:cards,card_number',
            'password' => 'required|string|min:8|confirmed',
            'fcm_token' => 'nullable|string',
        ];
    }
}
