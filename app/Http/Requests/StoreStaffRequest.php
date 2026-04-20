<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasRole('merchant');
    }

    public function rules(): array
    {
        if ($this->filled('user_id')) {
            return [
                'user_id' => 'required|exists:users,id',
                'buses' => 'nullable|array',
                'buses.*' => 'exists:buses,id',
                'parkings' => 'nullable|array',
                'parkings.*' => 'exists:parkings,id',
            ];
        }

        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'buses' => 'nullable|array',
            'buses.*' => 'exists:buses,id',
            'parkings' => 'nullable|array',
            'parkings.*' => 'exists:parkings,id',
        ];
    }
}
