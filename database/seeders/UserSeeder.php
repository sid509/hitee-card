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

        // 1. Super Admin
        $admin = User::create([
            'name' => 'Sudip Sharki',
            'email' => 'admin@hitee.ai',
            'password' => Hash::make('Admin@Hitee2026'),
            'status' => 'active',
        ]);
        $admin->roles()->attach($superAdminRole);

        // 2. Merchants (Familiar Nepali Business Names/Owners)
        $merchants = [
            ['name' => 'Ram Bahadur Thapa', 'email' => 'ram@merchant.com'],
            ['name' => 'Sita Kumari Dahal', 'email' => 'sita@merchant.com'],
            ['name' => 'Ganesh Prasad Bhatta', 'email' => 'ganesh@merchant.com'],
            ['name' => 'Maya Devi Sharma', 'email' => 'maya@merchant.com'],
            ['name' => 'Krishna Prasad Oli', 'email' => 'krishna@merchant.com'],
        ];

        foreach ($merchants as $m) {
            $user = User::create([
                'name' => $m['name'],
                'email' => $m['email'],
                'password' => Hash::make('password'),
                'status' => 'active',
            ]);
            $user->roles()->attach($merchantRole);
        }

        // 3. Customers (Familiar Nepali Names)
        $customers = [
            'Aayushma Regmi', 'Bipul Chhetri', 'Deepak Raj Giri', 'Ishani Shrestha', 
            'Milan Newar', 'Nabin K Bhattarai', 'Priyanka Karki', 'Rajesh Hamal', 
            'Sandeep Lamichhane', 'Shrinkhala Khatiwada', 'Sushant KC', 'Ujjwal Thapa', 
            'Anmol KC', 'Dayahang Rai', 'Keki Adhikari', 'Namrata Shrestha',
            'Pradeep Khadka', 'Saugat Malla', 'Swastima Khadka', 'Bipin Karki'
        ];

        foreach ($customers as $index => $name) {
            $email = strtolower(str_replace(' ', '.', $name)) . '@gmail.com';
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make('password'),
                'status' => $index % 10 == 0 ? 'inactive' : 'active',
            ]);
            $user->roles()->attach($customerRole);
        }
    }
}
