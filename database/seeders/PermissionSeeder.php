<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            ['name' => 'View Users', 'slug' => 'view-users'],
            ['name' => 'Create Users', 'slug' => 'create-users'],
            ['name' => 'Edit Users', 'slug' => 'edit-users'],
            ['name' => 'Delete Users', 'slug' => 'delete-users'],
            ['name' => 'Manage Roles', 'slug' => 'manage-roles'],
        ];

        foreach ($permissions as $permission) {
            Permission::create($permission);
        }

        // Give all permissions to super-admin
        $role = Role::where('slug', 'super-admin')->first();
        if ($role) {
            $role->permissions()->sync(Permission::all());
        }
    }
}
