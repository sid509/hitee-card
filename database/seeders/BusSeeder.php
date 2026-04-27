<?php

namespace Database\Seeders;

use App\Models\Bus;
use App\Models\User;
use Illuminate\Database\Seeder;

class BusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $merchants = User::whereHas('roles', fn($q) => $q->where('slug', 'merchant'))->get();
        $routes = \App\Models\Route::all();

        if ($merchants->isEmpty() || $routes->isEmpty()) {
            $this->command->warn('Merchants or Routes missing. Skipping Bus seeding.');
            return;
        }

        $busCompanies = [
            'Sajha Yatayat', 'Mahanagar Yatayat', 'Mayur Yatayat', 'Nepal Yatayat', 'City Yatayat',
            'Digo Yatayat', 'Orange Bus', 'Blue Sky Transport', 'Valley Connect'
        ];

        $prefixes = ['BA 2 KA', 'BA 3 KA', 'BA 4 KA', 'BA 1 KA', 'BA 5 KA'];

        foreach ($merchants as $merchant) {
            // Create 5-10 buses for each merchant
            $busCount = rand(5, 10);
            
            for ($i = 0; $i < $busCount; $i++) {
                $company = $busCompanies[array_rand($busCompanies)];
                $prefix = $prefixes[array_rand($prefixes)];
                $number = rand(1000, 9999);
                $route = $routes->random();

                $bus = Bus::create([
                    'name' => $company . ' #' . ($i + 1),
                    'bus_number' => $prefix . ' ' . $number,
                    'hwid' => 'HW_' . strtoupper(bin2hex(random_bytes(4))),
                    'status' => 'active',
                    'merchant_id' => $merchant->id,
                    'latitude' => 27.7 + (rand(-100, 100) / 1000),
                    'longitude' => 85.3 + (rand(-100, 100) / 1000),
                    'route_id' => $route->id,
                ]);

                // Assign an active fare based on the route
                $fare = \App\Models\Fare::where('route_id', $route->id)->first();
                if ($fare) {
                    $bus->update(['active_fare_id' => $fare->id]);
                }
            }
        }

        $this->command->info('Buses seeded successfully.');
    }
}
