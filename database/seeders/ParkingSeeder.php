<?php

namespace Database\Seeders;

use App\Models\Parking;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ParkingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $merchants = User::whereHas('roles', function($q){
            $q->where('slug', 'merchant');
        })->get();

        if ($merchants->isEmpty()) return;

        for ($i = 1; $i <= 20; $i++) {
            Parking::create([
                'name' => "Parking " . strtoupper(Str::random(5)),
                'location' => "Location " . rand(1, 50),
                'status' => rand(0, 1) ? 'opened' : 'closed',
                'merchant_id' => $merchants->random()->id,
            ]);
        }
    }
}
