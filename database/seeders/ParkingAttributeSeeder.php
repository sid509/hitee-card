<?php

namespace Database\Seeders;

use App\Models\ParkingAttribute;
use Illuminate\Database\Seeder;

class ParkingAttributeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $attributes = [
            'CCTV Surveillance',
            'EV Charging',
            '24/7 Access',
            'Covered Parking',
            'Security Guard',
            'Valet Service',
            'Disabled Access',
            'Fire Safety',
        ];

        foreach ($attributes as $name) {
            ParkingAttribute::firstOrCreate(['name' => $name]);
        }
    }
}
