<?php

namespace Database\Seeders;

use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Fare;
use App\Models\FareMatrix;
use App\Models\Bus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RouteFareSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $merchants = User::whereHas('roles', fn($q) => $q->where('slug', 'merchant'))->get();
        if ($merchants->isEmpty()) return;

        // --- Route 1: Ring Road - East Section ---
        $route1 = Route::create([
            'merchant_id' => $merchants[0]->id,
            'name' => 'Ring Road - East Section',
            'description' => 'Route covering Koteshwor to Chabahil section of Ring Road.',
        ]);

        $stops1 = [
            ['name' => 'Koteshwor', 'lat' => 27.675571, 'lng' => 85.345919],
            ['name' => 'Tinkune', 'lat' => 27.683344, 'lng' => 85.347514],
            ['name' => 'Sinamangal', 'lat' => 27.689623, 'lng' => 85.352458],
            ['name' => 'Gaushala', 'lat' => 27.707245, 'lng' => 85.347895],
            ['name' => 'Chabahil', 'lat' => 27.717245, 'lng' => 85.345960],
        ];

        $routeStops1 = [];
        foreach ($stops1 as $index => $s) {
            $routeStops1[] = RouteStop::create([
                'route_id' => $route1->id,
                'stop_name' => $s['name'],
                'latitude' => $s['lat'],
                'longitude' => $s['lng'],
                'order' => $index,
            ]);
        }

        $fare1 = Fare::create([
            'merchant_id' => $route1->merchant_id,
            'route_id' => $route1->id,
            'name' => 'Ring Road Standard Fare 2026',
            'status' => 'approved',
            'effective_from' => now()->subMonths(6),
        ]);

        foreach ($routeStops1 as $from) {
            foreach ($routeStops1 as $to) {
                if ($from->id != $to->id) {
                    $dist = abs($from->order - $to->order);
                    FareMatrix::create([
                        'fare_id' => $fare1->id,
                        'from_stop_id' => $from->id,
                        'to_stop_id' => $to->id,
                        'amount' => 20 + ($dist * 5),
                    ]);
                }
            }
        }

        // --- Route 2: Central Valley - West ---
        $route2 = Route::create([
            'merchant_id' => $merchants[1]->id,
            'name' => 'Kalanki - Ratnapark Express',
            'description' => 'Main thoroughfare from Kalanki to City Center.',
        ]);

        $stops2 = [
            ['name' => 'Kalanki', 'lat' => 27.686382, 'lng' => 85.289123],
            ['name' => 'Kalimati', 'lat' => 27.694684, 'lng' => 85.320481],
            ['name' => 'Tripureshwor', 'lat' => 27.693452, 'lng' => 85.311234],
            ['name' => 'Ratnapark', 'lat' => 27.700769, 'lng' => 85.313957],
        ];

        $routeStops2 = [];
        foreach ($stops2 as $index => $s) {
            $routeStops2[] = RouteStop::create([
                'route_id' => $route2->id,
                'stop_name' => $s['name'],
                'latitude' => $s['lat'],
                'longitude' => $s['lng'],
                'order' => $index,
            ]);
        }

        $fare2 = Fare::create([
            'merchant_id' => $route2->merchant_id,
            'route_id' => $route2->id,
            'name' => 'Central City Fare V1',
            'status' => 'approved',
            'effective_from' => now()->subMonths(6),
        ]);

        foreach ($routeStops2 as $from) {
            foreach ($routeStops2 as $to) {
                if ($from->id != $to->id) {
                    $dist = abs($from->order - $to->order);
                    FareMatrix::create([
                        'fare_id' => $fare2->id,
                        'from_stop_id' => $from->id,
                        'to_stop_id' => $to->id,
                        'amount' => 25 + ($dist * 10),
                    ]);
                }
            }
        }

        // --- Assign Fares to Existing Buses ---
        $buses = Bus::all();
        foreach ($buses as $bus) {
            // Distribute buses between the two routes
            $assignedRoute = ($bus->id % 2 == 0) ? $route1 : $route2;
            $assignedFare = ($bus->id % 2 == 0) ? $fare1 : $fare2;

            $bus->update([
                'merchant_id' => $assignedRoute->merchant_id, // Match merchant to route
                'route_id' => $assignedRoute->id,
                'active_fare_id' => $assignedFare->id,
            ]);
        }
    }
}
