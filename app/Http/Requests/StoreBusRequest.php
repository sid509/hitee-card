<?php

namespace App\Http\Requests;

/**
 * Validation for creating a new bus
 */
class StoreBusRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'bus_number' => 'required|string|unique:buses',
            'hwid' => 'required|string|unique:buses',
            'merchant_id' => 'nullable|exists:users,id',
            'status' => 'required|in:active,inactive',
            'featured_image' => 'nullable|image|max:2048',
            'gallery_images.*' => 'nullable|image|max:2048',
        ];
    }
}
