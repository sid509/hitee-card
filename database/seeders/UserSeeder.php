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
        $staffRole = Role::where('slug', 'staff')->first();

        // 1. Super Admin
        $admin = User::firstOrCreate(['email' => 'admin@hitee.ai'], [
            'name' => 'Sudip Dahal',
            'phone_number' => '9841273250',
            'password' => Hash::make('Admin@Hitee2026'),
            'status' => 'active',
            'email_verified_at' => now()
        ]);
        if (!$admin->hasRole('super-admin')) {
            $admin->roles()->attach($superAdminRole);
        }

        // 2. Merchants
        $merchants = [
            ['name' => 'Ram Bahadur Thapa', 'email' => 'ram@merchant.com', 'phone' => '9841000001', 'type' => 'bus_operator'],
            ['name' => 'Sita Kumari Dahal', 'email' => 'sita@merchant.com', 'phone' => '9841000002', 'type' => 'parking_operator'],
            ['name' => 'Ganesh Prasad Bhatta', 'email' => 'ganesh@merchant.com', 'phone' => '9841000003', 'type' => 'service_partner'],
            ['name' => 'Maya Devi Sharma', 'email' => 'maya@merchant.com', 'phone' => '9841000004', 'type' => 'service_partner'],
            ['name' => 'Krishna Prasad Oli', 'email' => 'krishna@merchant.com', 'phone' => '9841000005', 'type' => 'bus_operator'],
        ];

        foreach ($merchants as $m) {
            $user = User::firstOrCreate(['email' => $m['email']], [
                'name' => $m['name'],
                'phone_number' => $m['phone'],
                'password' => Hash::make('password'),
                'merchant_type' => $m['type'],
                'status' => 'active',
            ]);
            if (!$user->hasRole('merchant')) {
                $user->roles()->attach($merchantRole);
            }
        }

        // 3. Customers
        $customers = [
            ['name' => 'Aayushma Regmi', 'tourist' => false],
            ['name' => 'Bipul Chhetri', 'tourist' => false],
            ['name' => 'John Doe', 'tourist' => true],
            ['name' => 'Jane Smith', 'tourist' => true],
            ['name' => 'Milan Newar', 'tourist' => false],
            ['name' => 'Priyanka Karki', 'tourist' => false]
        ];

        foreach ($customers as $index => $c) {
            $email = strtolower(str_replace(' ', '.', $c['name'])) . '@gmail.com';
            $user = User::firstOrCreate(['email' => $email], [
                'name' => $c['name'],
                'phone_number' => '9860' . str_pad($index, 6, '0', STR_PAD_LEFT),
                'password' => Hash::make('password'),
                'is_tourist' => $c['tourist'],
                'status' => $index % 5 == 0 ? 'inactive' : 'active',
            ]);

            // Create KYC verification
            if (!$user->kycVerification()->exists()) {
                \App\Models\KycVerification::create([
                    'user_id' => $user->id,
                    'full_name' => $user->name,
                    'id_type' => 'Citizenship',
                    'id_number' => 'CTZ-' . rand(1000, 9999),
                    'kyc_type' => 'standard',
                    'status' => $index % 2 == 0 ? 'approved' : 'requested',
                    'verified_at' => $index % 2 == 0 ? now() : null,
                ]);
            }

            if (!$user->hasRole('customers')) {
                $user->roles()->attach($customerRole);
            }
        }
    }
}
