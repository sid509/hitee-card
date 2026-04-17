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

        if ($merchants->isEmpty()) return;

        $kathmanduBuses = [
            ['name' => 'Sajha Yatayat', 'no' => 'BA 2 KA 1234', 'lat' => 27.700769, 'lng' => 85.313957], // Ratnapark
            ['name' => 'Mahanagar Yatayat', 'no' => 'BA 3 KA 5678', 'lat' => 27.694684, 'lng' => 85.320481], // Kalimati
            ['name' => 'Mayur Yatayat', 'no' => 'BA 4 KA 9012', 'lat' => 27.675571, 'lng' => 85.345919], // Koteshwor
            ['name' => 'Nepal Yatayat', 'no' => 'BA 1 KA 3344', 'lat' => 27.717245, 'lng' => 85.323960], // Lazimpat
            ['name' => 'City Yatayat', 'no' => 'BA 5 KA 7788', 'lat' => 27.686382, 'lng' => 85.289123], // Kalanki
        ];

        foreach ($kathmanduBuses as $index => $data) {
            Bus::create([
                'name' => $data['name'],
                'bus_number' => $data['no'],
                'hwid' => 'HW_' . uniqid(),
                'status' => 'active',
                'merchant_id' => $merchants->random()->id,
                'latitude' => $data['lat'],
                'longitude' => $data['lng'],
            ]);
        }
    }
}
