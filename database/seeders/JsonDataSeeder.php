<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Stop;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Fare;
use App\Models\FareMatrix;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

class JsonDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting High-Fidelity JSON Data Ingestion...');

        // 1. Seed Stops
        $stopsJson = File::get(database_path('data/stops.json'));
        $stopsData = json_decode($stopsJson, true);

        $this->command->info('Seeding ' . count($stopsData) . ' unique stops...');
        $stopModels = [];
        foreach ($stopsData as $data) {
            $stopModels[$data['name']] = Stop::updateOrCreate(
                ['name' => $data['name']],
                ['latitude' => $data['lat'], 'longitude' => $data['lng']]
            );
        }

        // 2. Seed Routes and generate Inbound/Outbound
        $routesJson = File::get(database_path('data/routes.json'));
        $routesData = json_decode($routesJson, true);

        $merchants = User::whereHas('roles', fn($q) => $q->where('slug', 'merchant'))->get();
        
        $this->command->info('Seeding ' . (count($routesData) * 2) . ' routes (Inbound & Outbound)...');

        foreach ($routesData as $data) {
            // Optional: Match to an existing merchant if name matches, else assign none or random
            $merchant = $merchants->random(); 

            // --- OUTBOUND ROUTE ---
            $outbound = Route::create([
                'name' => $data['service_name'] . " (Going)",
                'description' => "Official route code: " . $data['route_code'],
                'direction' => 'inbound'
            ]);

            $outRouteStops = [];
            foreach ($data['stops'] as $index => $stopName) {
                if (!isset($stopModels[$stopName])) {
                    $this->command->warn("Stop '{$stopName}' not found in stops.json. Skipping for route '{$data['service_name']}'.");
                    continue;
                }
                $stop = $stopModels[$stopName];
                $outRouteStops[] = RouteStop::create([
                    'route_id' => $outbound->id,
                    'stop_id' => $stop->id,
                    'stop_name' => $stop->name,
                    'latitude' => $stop->latitude,
                    'longitude' => $stop->longitude,
                    'order' => $index,
                ]);
            }

            // Create Fare for Outbound
            $this->createStandardFare($outbound, $outRouteStops);

            // --- INBOUND ROUTE (REVERSED) ---
            $inbound = Route::create([
                'name' => $data['service_name'] . " (Return)",
                'description' => "Official route code: " . $data['route_code'],
                'direction' => 'outbound'
            ]);

            $inRouteStops = [];
            $reversedStops = array_reverse($data['stops']);
            foreach ($reversedStops as $index => $stopName) {
                if (!isset($stopModels[$stopName])) continue;
                $stop = $stopModels[$stopName];
                $inRouteStops[] = RouteStop::create([
                    'route_id' => $inbound->id,
                    'stop_id' => $stop->id,
                    'stop_name' => $stop->name,
                    'latitude' => $stop->latitude,
                    'longitude' => $stop->longitude,
                    'order' => $index,
                ]);
            }

            // Create Fare for Inbound
            $this->createStandardFare($inbound, $inRouteStops);
        }

        $this->command->info('JSON Data Ingestion Completed.');
    }

    private function createStandardFare($route, $routeStops)
    {
        $fare = Fare::create([
            'route_id' => $route->id,
            'name' => $route->name . " Standard Fare",
            'status' => 'approved',
            'effective_from' => now()->subMonths(6)
        ]);

        // Link the same merchants as the route

        // Populate Matrix
        foreach ($routeStops as $from) {
            foreach ($routeStops as $to) {
                if ($from->id != $to->id) {
                    $dist = abs($from->order - $to->order);
                    FareMatrix::create([
                        'fare_id' => $fare->id,
                        'from_stop_id' => $from->id,
                        'to_stop_id' => $to->id,
                        'amount' => 20 + ($dist * 2), // Standard city pricing simulation
                    ]);
                }
            }
        }
    }
}
