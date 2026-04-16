<?php

namespace App\Http\Requests;

/**
 * Validation for creating a new permission
 */
class StorePermissionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:permissions',
        ];
    }
}
