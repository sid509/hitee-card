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
            'first_hour_fee' => 'required|numeric|min:0',
            'onwards_hour_fee' => 'required|numeric|min:0',
            'attributes' => 'nullable|array',
            'attributes.*' => 'exists:parking_attributes,id',
            'featured_image' => 'nullable|image|max:2048',
            'gallery_images.*' => 'nullable|image|max:2048',
        ];
    }
}
