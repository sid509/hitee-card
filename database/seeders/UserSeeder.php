<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure roles exist
        if (Role::count() === 0) {
            $this->call(RoleSeeder::class);
        }

        $superAdminRole = Role::where('slug', 'super-admin')->first();
        $merchantRole = Role::where('slug', 'merchant')->first();
        $customerRole = Role::where('slug', 'customers')->first();

        // 1 Super Admin
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@hitee.ai',
            'password' => Hash::make('Admin@Hitee2026'),
            'status' => 'active',
        ]);
        $admin->roles()->attach($superAdminRole);


        // 5 Merchants
        for ($i = 1; $i <= 5; $i++) {
            $merchant = User::create([
                'name' => "Merchant $i",
                'email' => "merchant$i@hitee.ai",
                'password' => Hash::make('password'),
                'status' => 'active',
            ]);
            $merchant->roles()->attach($merchantRole);
        }

        // 14 Customers
        for ($i = 1; $i <= 14; $i++) {
            $customer = User::create([
                'name' => "Customer $i",
                'email' => "customer$i@hitee.ai",
                'password' => Hash::make('password'),
                'status' => $i % 5 == 0 ? 'inactive' : 'active',
            ]);
            $customer->roles()->attach($customerRole);
        }
    }
}
