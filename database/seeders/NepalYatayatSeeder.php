<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Stop;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;

class NepalYatayatSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = User::whereHas('roles', fn($q) => $q->where('slug', 'merchant'))->inRandomOrder()->first();
        if (!$merchant) return;

        $this->command->info('Seeding Nepal Yatayat Route...');

        $route = Route::create([
            'name' => 'Nepal Yatayat - Airport to Kalanki',
            'description' => 'Connects the Airport to the western entry point of the valley.',
        ]);


        $stopsData = [
            ['name' => 'Airport'], ['name' => 'Gaushala'], ['name' => 'Ratnapark'], ['name' => 'Tripureshwor'], ['name' => 'Kalimati'], ['name' => 'Kalanki'],
        ];

        foreach ($stopsData as $index => $stopInfo) {
            $stop = Stop::updateOrCreate(
                ['name' => $stopInfo['name']],
                ['latitude' => 27.69 - ($index * 0.001), 'longitude' => 85.35 - ($index * 0.002)] // Placeholder coords
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
