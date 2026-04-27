<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Stop;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;

class MayurYatayatSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = User::whereHas('roles', fn($q) => $q->where('slug', 'merchant'))->inRandomOrder()->first();
        if (!$merchant) return;

        $this->command->info('Seeding Mayur Yatayat Route...');

        $route = Route::create([
            'name' => 'Mayur Yatayat - Ring Road to Bhaktapur',
            'description' => 'Route from Ring Road to Suryabinayak, Bhaktapur.',
        ]);


        $stopsData = [
            ['name' => 'Koteshwor'], ['name' => 'Jadibuti'], ['name' => 'Lokanthali'], ['name' => 'Kaushaltar'], ['name' => 'Gatthaghar'], ['name' => 'Thimi'], ['name' => 'Suryabinayak'],
        ];

        foreach ($stopsData as $index => $stopInfo) {
            $stop = Stop::updateOrCreate(
                ['name' => $stopInfo['name']],
                ['latitude' => 27.67 + ($index * 0.002), 'longitude' => 85.35 + ($index * 0.002)] // Placeholder coords
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
