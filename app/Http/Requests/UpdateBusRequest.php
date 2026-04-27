<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * Validation for updating an existing bus
 */
class UpdateBusRequest extends BaseRequest
{
    public function rules(): array
    {
        $bus = $this->route('bus');
        $busId = $bus ? $bus->id : null;

        $rules = [
            'name' => 'required|string|max:255',
            'status' => 'required|in:active,inactive',
            'route_id' => 'nullable|exists:routes,id',
            'featured_image' => 'nullable|image|max:2048',
            'gallery_images.*' => 'nullable|image|max:2048',
        ];

        // Only Super Admin can update these fields usually, but we define the rules here
        // The controller will handle the authorization and which fields to update
        if (auth()->user()->hasRole('super-admin')) {
            $rules['bus_number'] = [
                'required',
                'string',
                Rule::unique('buses')->ignore($busId),
            ];
            $rules['hwid'] = [
                'required',
                'string',
                Rule::unique('buses')->ignore($busId),
            ];
            $rules['merchant_id'] = 'nullable|exists:users,id';
        }

        return $rules;
    }
}
