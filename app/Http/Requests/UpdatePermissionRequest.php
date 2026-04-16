<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * Validation for updating an existing permission
 */
class UpdatePermissionRequest extends BaseRequest
{
    public function rules(): array
    {
        $permission = $this->route('permission');
        $permissionId = $permission ? $permission->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('permissions')->ignore($permissionId),
            ],
        ];
    }
}
