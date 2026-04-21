<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Stop;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;

class ComprehensiveRouteSeeder extends Seeder
{
    public function run(): void
    {
        $merchants = User::whereHas('roles', fn($q) => $q->where('slug', 'merchant'))->get();
        if ($merchants->isEmpty()) return;

        $this->command->info('Seeding High-Fidelity Kathmandu Transit Network...');

        // 1. COMPREHENSIVE STOP REGISTRY
        // Centralized Hubs and sequential stops along major corridors
        $stopRegistry = [
            // --- RING ROAD CORRIDOR ---
            'Kalanki' => [27.6946, 85.2818],
            'Balkhu' => [27.6835, 85.2929],
            'Kuleshwor' => [27.6903, 85.2952],
            'Sanepa' => [27.6810, 85.3020],
            'Ekantakuna' => [27.6725, 85.3072],
            'Satdobato' => [27.6617, 85.3214],
            'Gwarko' => [27.6678, 85.3347],
            'Koteshwor' => [27.6756, 85.3459],
            'Tinkune' => [27.6833, 85.3475],
            'Sinamangal' => [27.6890, 85.3520],
            'Gaushala' => [27.7072, 85.3479],
            'Chabahil' => [27.7172, 85.3459],
            'Sukedhara' => [27.7280, 85.3400],
            'Maharajgunj' => [27.7344, 85.3325],
            'Basundhara' => [27.7383, 85.3216],
            'Samakhusi' => [27.7280, 85.3120],
            'Gongabu' => [27.7348, 85.3089],
            'Balaju' => [27.7258, 85.2974],
            'Swayambhu' => [27.7147, 85.2906],
            'Sitapaila' => [27.7088, 85.2816],

            // --- CITY CORE CORRIDORS ---
            'Ratnapark' => [27.7062, 85.3149],
            'Jamal' => [27.7085, 85.3155],
            'Lainchaur' => [27.7130, 85.3150],
            'Lazimpat' => [27.7190, 85.3200],
            'Teaching Hospital' => [27.7280, 85.3300],
            'Tripureshwor' => [27.6934, 85.3112],
            'Kalimati' => [27.6946, 85.3204],
            'Teku' => [27.6930, 85.3150],
            'Thapathali' => [27.6890, 85.3180],
            'Maitighar' => [27.6920, 85.3230],
            'New Baneshwor' => [27.6915, 85.3335],
            'Shantinagar' => [27.6870, 85.3430],
            'Bagbazar' => [27.7058, 85.3193],
            'Putalisadak' => [27.7032, 85.3214],

            // --- PERIPHERAL ENDS ---
            'Budhanilkantha' => [27.7770, 85.3600],
            'Jorpati' => [27.7210, 85.3750],
            'Kamalbinayak' => [27.6770, 85.4380],
            'Suryabinayak' => [27.6650, 85.4200],
            'Thimi' => [27.6760, 85.3900],
            'Jadibuti' => [27.6740, 85.3580],
            'Airport' => [27.6980, 85.3590],
            'Lagankhel' => [27.6660, 85.3240],
            'Jawalakhel' => [27.6730, 85.3130],
            'Kirtipur' => [27.6780, 85.2850],
            'Thankot' => [27.6850, 85.2050],
        ];

        $stops = [];
        foreach ($stopRegistry as $name => $coords) {
            $stops[$name] = Stop::updateOrCreate(
                ['name' => $name],
                ['latitude' => $coords[0], 'longitude' => $coords[1]]
            );
        }

        // 2. COMPREHENSIVE ROUTE NETWORK (Dynamic Array from Map Data)
        $routeDefinitions = [
            [
                'name' => 'Sajha Yatayat (S1)',
                'merchant' => 'Sajha Yatayat',
                'path' => ['Lagankhel', 'Jawalakhel', 'Tripureshwor', 'Ratnapark', 'Lainchaur', 'Lazimpat', 'Teaching Hospital', 'Maharajgunj', 'Basundhara', 'Gongabu']
            ],
            [
                'name' => 'Nepal Yatayat (N2)',
                'merchant' => 'Nepal Yatayat',
                'path' => ['Jadibuti', 'Koteshwor', 'Tinkune', 'Shantinagar', 'New Baneshwor', 'Maitighar', 'Thapathali', 'Tripureshwor', 'Teku', 'Kalimati', 'Kalanki']
            ],
            [
                'name' => 'Mayur Yatayat (X)',
                'merchant' => 'Mayur Yatayat',
                'path' => ['Kamalbinayak', 'Suryabinayak', 'Thimi', 'Jadibuti', 'Koteshwor', 'Tinkune', 'Shantinagar', 'New Baneshwor', 'Maitighar', 'Ratnapark', 'Jamal', 'Lainchaur', 'Balaju', 'Gongabu']
            ],
            [
                'name' => 'Mahanagar Yatayat (M)',
                'merchant' => 'Mahanagar Yatayat',
                'path' => ['Kalanki', 'Balkhu', 'Sanepa', 'Ekantakuna', 'Satdobato', 'Gwarko', 'Koteshwor', 'Tinkune', 'Sinamangal', 'Gaushala', 'Chabahil', 'Sukedhara', 'Maharajgunj', 'Basundhara', 'Gongabu', 'Balaju', 'Swayambhu', 'Sitapaila', 'Kalanki']
            ],
            [
                'name' => 'Samakhusi Yatayat (Z)',
                'merchant' => 'Samakhusi Yatayat',
                'path' => ['Ratnapark', 'Jamal', 'Lainchaur', 'Basundhara', 'Samakhusi', 'Gongabu']
            ],
            [
                'name' => 'Budhanilkantha Yatayat (A)',
                'merchant' => 'Budhanilkantha Yatayat',
                'path' => ['Ratnapark', 'Jamal', 'Lainchaur', 'Lazimpat', 'Teaching Hospital', 'Maharajgunj', 'Budhanilkantha']
            ],
            [
                'name' => 'Bhaktapur Buses (B)',
                'merchant' => 'Bhaktapur Transport',
                'path' => ['Kamalbinayak', 'Suryabinayak', 'Thimi', 'Jadibuti', 'Koteshwor', 'Tinkune', 'Shantinagar', 'New Baneshwor', 'Maitighar', 'Ratnapark']
            ],
            [
                'name' => 'Kirtipur Yatayat (K)',
                'merchant' => 'Kirtipur Yatayat',
                'path' => ['Kirtipur', 'Balkhu', 'Kuleshwor', 'Kalimati', 'Teku', 'Tripureshwor', 'Ratnapark']
            ],
            [
                'name' => 'Samyukta Yatayat (Y)',
                'merchant' => 'Samyukta Yatayat',
                'path' => ['Kalanki', 'Kalimati', 'Teku', 'Tripureshwor', 'Ratnapark', 'Bagbazar', 'Putalisadak', 'Lainchaur', 'Balaju', 'Gongabu']
            ]
        ];

        foreach ($routeDefinitions as $def) {
            $merchant = User::where('name', 'LIKE', "%{$def['merchant']}%")->first() ?? $merchants->random();

            // --- SEED OUTBOUND ---
            $outbound = Route::create([
                'name' => "{$def['name']} - Going",
                'description' => "Main outbound line for {$def['name']}",
                'direction' => 'inbound'
            ]);
            $outbound->merchants()->attach($merchant->id);

            foreach ($def['path'] as $index => $stopName) {
                RouteStop::create([
                    'route_id' => $outbound->id,
                    'stop_id' => $stops[$stopName]->id,
                    'stop_name' => $stopName,
                    'latitude' => $stops[$stopName]->latitude,
                    'longitude' => $stops[$stopName]->longitude,
                    'order' => $index,
                ]);
            }

            // --- SEED INBOUND (RETURN) ---
            $inbound = Route::create([
                'name' => "{$def['name']} - Return",
                'description' => "Main inbound line for {$def['name']}",
                'direction' => 'outbound'
            ]);
            $inbound->merchants()->attach($merchant->id);

            $reversedPath = array_reverse($def['path']);
            foreach ($reversedPath as $index => $stopName) {
                RouteStop::create([
                    'route_id' => $inbound->id,
                    'stop_id' => $stops[$stopName]->id,
                    'stop_name' => $stopName,
                    'latitude' => $stops[$stopName]->latitude,
                    'longitude' => $stops[$stopName]->longitude,
                    'order' => $index,
                ]);
            }
        }

        $this->command->info('Comprehensive Seeding Completed.');
    }
}
