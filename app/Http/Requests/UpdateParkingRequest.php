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
            'total_capacity' => 'required|integer|min:0',
            'status' => 'required|in:opened,closed',
            'first_hour_fee' => 'required|numeric|min:0',
            'onwards_hour_fee' => 'required|numeric|min:0',
            'attributes' => 'nullable|array',
            'attributes.*' => 'exists:parking_attributes,id',
            'featured_image' => 'nullable|image|max:2048',
            'gallery_images.*' => 'nullable|image|max:2048',
        ];

        if (auth()->user()->hasRole('super-admin')) {
            $rules['merchant_id'] = 'nullable|exists:users,id';
        }

        return $rules;
    }
}
