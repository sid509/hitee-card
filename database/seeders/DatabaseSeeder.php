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
            
            // 1. Create Routes and their Stops
            RingRoadSeeder::class,
            MayurYatayatSeeder::class,
            MahanagarYatayatSeeder::class,
            NepalYatayatSeeder::class,
            CityCoreSeeder::class,
            
            // 2. Create Buses and link to Routes
            BusSeeder::class,
            
            // 3. Create Fares for those Routes and link to Buses
            FareSeeder::class,
            
            // 4. Create other assets
            ParkingSeeder::class,
            ParkingAttributeSeeder::class,
            StaffSeeder::class,
            
            // 5. Final Step: Simulation (requires Buses with Fares and Customers with Cards)
            CardSeeder::class,
            UltraRealisticTransitSeeder::class,
        ]);
    }
}
