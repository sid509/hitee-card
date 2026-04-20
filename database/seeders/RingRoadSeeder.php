<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Stop;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;

class RingRoadSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = User::whereHas('roles', fn($q) => $q->where('slug', 'merchant'))->first();
        if (!$merchant) return;

        $this->command->info('Seeding Ring Road Route with realistic coordinates...');

        $route = Route::create([
            'name' => 'Ring Road Full Circuit',
            'description' => 'Complete circuit of Kathmandu\'s Ring Road.',
        ]);

        $route->merchants()->attach($merchant->id);

        // More realistic, circular coordinates for Ring Road stops
        $stopsData = [
            ['name' => 'Kalanki', 'lat' => 27.6946, 'lng' => 85.2818],
            ['name' => 'Balkhu', 'lat' => 27.6835, 'lng' => 85.2929],
            ['name' => 'Kuleshwor', 'lat' => 27.6903, 'lng' => 85.2952],
            ['name' => 'Dhobighat', 'lat' => 27.6783, 'lng' => 85.3053], // Added Sanepa/Dhobighat
            ['name' => 'Ekantakuna', 'lat' => 27.6725, 'lng' => 85.3072],
            ['name' => 'Satdobato', 'lat' => 27.6617, 'lng' => 85.3214],
            ['name' => 'Gwarko', 'lat' => 27.6678, 'lng' => 85.3347],
            ['name' => 'Koteshwor', 'lat' => 27.6756, 'lng' => 85.3459],
            ['name' => 'Tinkune', 'lat' => 27.6833, 'lng' => 85.3475],
            ['name' => 'Sinamangal', 'lat' => 27.6930, 'lng' => 85.3533],
            ['name' => 'Gaushala', 'lat' => 27.7072, 'lng' => 85.3479],
            ['name' => 'Chabahil', 'lat' => 27.7172, 'lng' => 85.3459],
            ['name' => 'Gopikrishna', 'lat' => 27.7248, 'lng' => 85.3421],
            ['name' => 'Maharajgunj', 'lat' => 27.7344, 'lng' => 85.3325],
            ['name' => 'Basundhara', 'lat' => 27.7383, 'lng' => 85.3216],
            ['name' => 'Gongabu', 'lat' => 27.7348, 'lng' => 85.3089],
            ['name' => 'Balaju', 'lat' => 27.7258, 'lng' => 85.2974],
            ['name' => 'Swayambhu', 'lat' => 27.7147, 'lng' => 85.2906],
            ['name' => 'Sitapaila', 'lat' => 27.7088, 'lng' => 85.2816],
        ];

        foreach ($stopsData as $index => $stopInfo) {
            $stop = Stop::updateOrCreate(
                ['name' => $stopInfo['name']],
                ['latitude' => $stopInfo['lat'], 'longitude' => $stopInfo['lng']]
            );
            RouteStop::create([
                'route_id' => $route->id,
                'stop_id' => $stop->id,
                'stop_name' => $stop->name,
                'latitude' => $stop->latitude,
                'longitude' => $stop->longitude,
                'order' => $index,
            ]);
        }
    }
}
