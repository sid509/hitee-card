<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasRole('merchant');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $this->staff->id,
            'password' => 'nullable|min:8',
            'buses' => 'nullable|array',
            'buses.*' => 'exists:buses,id',
            'parkings' => 'nullable|array',
            'parkings.*' => 'exists:parkings,id',
        ];
    }
}
