<?php

namespace Database\Seeders;

use App\Models\ServicePartner;
use App\Models\User;
use Illuminate\Database\Seeder;

class ServicePartnerSeeder extends Seeder
{
    public function run(): void
    {
        // Get a merchant user
        $merchant = User::whereHas('roles', function($q) {
            $q->where('slug', 'merchant');
        })->where('merchant_type', 'service_partner')->first();

        if (!$merchant) {
            return;
        }

        $partners = [
            [
                'merchant_id' => $merchant->id,
                'name' => 'Himalayan Java Coffee',
                'service_type' => 'restaurant',
                'address' => 'Thamel, Kathmandu',
                'latitude' => 27.7144,
                'longitude' => 85.3123,
                'status' => 'active',
            ],
            [
                'merchant_id' => $merchant->id,
                'name' => 'Bhatbhateni Supermarket',
                'service_type' => 'retail',
                'address' => 'Naxal, Kathmandu',
                'latitude' => 27.7188,
                'longitude' => 85.3308,
                'status' => 'active',
            ]
        ];

        foreach ($partners as $p) {
            ServicePartner::create($p);
        }
    }
}
