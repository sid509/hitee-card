<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Stop;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;

class CityCoreSeeder extends Seeder
{
    public function run(): void
    {
        $merchants = User::whereHas('roles', fn($q) => $q->where('slug', 'merchant'))->get();
        if ($merchants->isEmpty()) return;

        $this->command->info('Seeding City Core Circular Route...');

        $route = Route::create([
            'name' => 'City Core Circular (Ratnapark - Putalisadak)',
            'description' => 'A frequent circular route covering major city hubs.',
        ]);

        // Link multiple merchants to this route for simulation
        $route->merchants()->attach($merchants->pluck('id')->take(3));

        $stopsData = [
            ['name' => 'Ratnapark', 'lat' => 27.7062, 'lng' => 85.3149],
            ['name' => 'Bagbazar', 'lat' => 27.7058, 'lng' => 85.3193],
            ['name' => 'Putalisadak', 'lat' => 27.7032, 'lng' => 85.3214],
            ['name' => 'Padmodaya', 'lat' => 27.7011, 'lng' => 85.3198],
            ['name' => 'Bhadrakali', 'lat' => 27.7001, 'lng' => 85.3145],
            ['name' => 'Sahid Gate', 'lat' => 27.7025, 'lng' => 85.3125],
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
