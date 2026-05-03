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
            'fees' => 'required|array|min:1',
            'fees.*.title' => 'required|string|max:255',
            'fees.*.subtitle' => 'nullable|string|max:255',
            'fees.*.price_rs' => 'required|numeric|min:0',
            'fees.*.price_pts' => 'required|numeric|min:0',
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
