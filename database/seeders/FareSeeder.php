<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Route;
use App\Models\Fare;
use App\Models\FareMatrix;
use App\Models\Bus;

class FareSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $routes = Route::with('stops')->get();
        
        if ($routes->isEmpty()) {
            $this->command->warn('No routes found. Skipping Fare seeding.');
            return;
        }

        $this->command->info('Seeding Fares and Matrices for ' . $routes->count() . ' routes...');

        foreach ($routes as $route) {
            $fare = Fare::create([
                'route_id' => $route->id,
                'name' => $route->name . ' - Standard Fare 2026',
                'status' => 'approved',
                'effective_from' => now()->subMonths(3),
            ]);

            $stops = $route->stops;
            foreach ($stops as $from) {
                foreach ($stops as $to) {
                    if ($from->id != $to->id) {
                        $orderDiff = abs($from->order - $to->order);
                        
                        // Base fare calculation
                        $amount = 20 + ($orderDiff * 5);

                        FareMatrix::create([
                            'fare_id' => $fare->id,
                            'from_stop_id' => $from->id,
                            'to_stop_id' => $to->id,
                            'amount' => $amount,
                        ]);
                    }
                }
            }

            // Assign this fare to buses on this route
            Bus::where('route_id', $route->id)->update(['active_fare_id' => $fare->id]);
        }

        $this->command->info('Fare seeding completed.');
    }
}
