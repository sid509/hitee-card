<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Stop;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;

class MahanagarYatayatSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = User::whereHas('roles', fn($q) => $q->where('slug', 'merchant'))->inRandomOrder()->first();
        if (!$merchant) return;

        $this->command->info('Seeding Mahanagar Yatayat Route...');

        $route = Route::create([
            'name' => 'Mahanagar Yatayat - City Core',
            'description' => 'Route covering central Kathmandu areas.',
        ]);


        $stopsData = [
            ['name' => 'Ratnapark'], ['name' => 'Jamal'], ['name' => 'Lainchaur'], ['name' => 'Lazimpat'], ['name' => 'Maharajgunj'],
        ];

        foreach ($stopsData as $index => $stopInfo) {
            $stop = Stop::updateOrCreate(
                ['name' => $stopInfo['name']],
                ['latitude' => 27.7 + ($index * 0.0015), 'longitude' => 85.31 - ($index * 0.0015)] // Placeholder coords
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
