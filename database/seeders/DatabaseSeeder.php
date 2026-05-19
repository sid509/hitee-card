<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SettingSeeder::class, // Run settings first to populate global variables
            BannerSeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
            SubscriptionModelSeeder::class,
            NotificationTemplateSeeder::class,
            
            // 1. COMPREHENSIVE DATA INGESTION
            JsonDataSeeder::class,

            // 2. Create Buses and link to Routes
            BusSeeder::class,

            // 3. Create other assets
            ParkingSeeder::class,
            ParkingAttributeSeeder::class,
            ServicePartnerSeeder::class,
            StaffSeeder::class,

            // 4. Create card applications
            CardApplicationSeeder::class,

            // 5. Final Step: Simulation (requires Buses with Fares and Customers with Cards)
            CardSeeder::class,
            UltraRealisticTransitSeeder::class,
        ]);
        
        $this->command->info('Database thoroughly seeded with all modules!');
    }
}
