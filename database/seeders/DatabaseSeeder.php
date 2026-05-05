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

            // 1. COMPREHENSIVE DATA INGESTION
            // This replaces individual company seeders with the full JSON dataset
            JsonDataSeeder::class,

            // 2. Create Buses and link to Routes
            BusSeeder::class,

            // 3. Create other assets
            ParkingSeeder::class,
            ParkingAttributeSeeder::class,
            StaffSeeder::class,

            // 4. Final Step: Simulation (requires Buses with Fares and Customers with Cards)
            CardSeeder::class,
            UltraRealisticTransitSeeder::class,
        ]);
    }
}
