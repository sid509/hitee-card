<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * Validation for updating an existing role
 */
class UpdateRoleRequest extends BaseRequest
{
    public function rules(): array
    {
        $role = $this->route('role');
        $roleId = $role ? $role->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles')->ignore($roleId),
            ],
        ];
    }
}
