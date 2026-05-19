<?php

namespace Database\Seeders;

use App\Models\SubscriptionModel;
use Illuminate\Database\Seeder;

class SubscriptionModelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SubscriptionModel::updateOrCreate(
            ['name' => 'Hitee Transit'],
            ['category' => 'transit', 'price' => 0.00, 'is_active' => true]
        );

        SubscriptionModel::updateOrCreate(
            ['name' => 'Hitee Dine In'],
            ['category' => 'dine_in', 'price' => 500.00, 'is_active' => true] // Example price
        );
    }
}
