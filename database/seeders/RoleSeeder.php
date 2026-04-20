<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'Super Admin', 'slug' => 'super-admin'],
            ['name' => 'Merchant', 'slug' => 'merchant'],
            ['name' => 'Staff', 'slug' => 'staff'],
            ['name' => 'Customers', 'slug' => 'customers'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['slug' => $role['slug']], $role);
        }

        // Assign super-admin to the first user
        $admin = User::where('email', 'admin@example.com')->first();
        if ($admin) {
            $role = Role::where('slug', 'super-admin')->first();
            $admin->roles()->attach($role);
        }
    }
}
