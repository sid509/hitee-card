<?php

namespace Database\Seeders;

use App\Models\Parking;
use App\Models\User;
use Illuminate\Database\Seeder;

class ParkingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $merchants = User::whereHas('roles', fn($q) => $q->where('slug', 'merchant'))->get();

        if ($merchants->isEmpty()) return;

        $kathmanduParkings = [
            ['name' => 'Civil Mall Parking', 'loc' => 'Sundhara, Kathmandu', 'lat' => 27.699926, 'lng' => 85.312157],
            ['name' => 'Labim Mall Parking', 'loc' => 'Pulchowk, Lalitpur', 'lat' => 27.677568, 'lng' => 85.316824],
            ['name' => 'Durbar Marg Parking', 'loc' => 'Durbar Marg, Kathmandu', 'lat' => 27.710787, 'lng' => 85.315939],
            ['name' => 'Bhat-Bhateni Koteshwor', 'loc' => 'Koteshwor, Kathmandu', 'lat' => 27.674931, 'lng' => 85.347514],
            ['name' => 'Basantapur Square', 'loc' => 'Basantapur, Kathmandu', 'lat' => 27.704193, 'lng' => 85.306540],
        ];

        foreach ($kathmanduParkings as $data) {
            Parking::create([
                'name' => $data['name'],
                'location' => $data['loc'],
                'status' => 'opened',
                'merchant_id' => $merchants->random()->id,
                'latitude' => $data['lat'],
                'longitude' => $data['lng'],
                'first_hour_fee' => rand(20, 50),
                'onwards_hour_fee' => rand(10, 30),
            ]);
        }
    }
}
