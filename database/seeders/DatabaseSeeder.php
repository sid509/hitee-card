<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
            RouteFareSeeder::class,
            BusSeeder::class,
            ParkingSeeder::class,
            ParkingAttributeSeeder::class,
            CardSeeder::class,
            BalanceSeeder::class,
            TapSeeder::class,
        ]);
    }
}
