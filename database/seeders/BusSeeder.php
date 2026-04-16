<?php

namespace Database\Seeders;

use App\Models\Bus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BusSeeder extends Seeder
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
            Bus::create([
                'name' => "Bus " . strtoupper(Str::random(5)),
                'bus_number' => "BA-" . rand(1000, 9999),
                'hwid' => "BUS-HW-" . Str::random(8),
                'status' => rand(0, 1) ? 'active' : 'inactive',
                'merchant_id' => $merchants->random()->id,
            ]);
        }
    }
}
