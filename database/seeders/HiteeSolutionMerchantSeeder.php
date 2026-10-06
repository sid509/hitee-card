<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class HiteeSolutionMerchantSeeder extends Seeder
{
    public const EMAIL = 'hiteesolution@hitee.ai';

    /**
     * The platform-owned merchant that imported parking lots are assigned to.
     */
    public function run(): void
    {
        $merchantRole = Role::firstOrCreate(['slug' => 'merchant'], ['name' => 'Merchant']);

        $merchant = User::firstOrCreate(['email' => self::EMAIL], [
            'name' => 'Hitee Solution',
            'phone_number' => '9800000000',
            'password' => Hash::make('password'),
            'merchant_type' => 'parking_operator',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        if (!$merchant->hasRole('merchant')) {
            $merchant->roles()->attach($merchantRole);
        }
    }
}
