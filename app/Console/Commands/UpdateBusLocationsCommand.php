<?php

namespace App\Console\Commands;

use App\Models\Bus;
use App\Models\BusLocation;
use App\Models\RouteStop;
use Illuminate\Console\Command;

class UpdateBusLocationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bus:update-locations {--bus_id= : Update location for a specific bus}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Randomly update bus locations following their assigned routes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $busId = $this->option('bus_id');
        
        $query = Bus::where('status', 'active')->whereNotNull('route_id');
        
        if ($busId) {
            $query->where('id', $busId);
        }

        $buses = $query->get();

        if ($buses->isEmpty()) {
            $this->info('No active buses found to update.');
            return;
        }

        foreach ($buses as $bus) {
            $currentPos = $bus->currentPosition;
            $stops = RouteStop::where('route_id', $bus->route_id)->orderBy('order')->get();

            if ($stops->isEmpty()) continue;

            $nextStop = null;
            $currentLat = null;
            $currentLng = null;

            if (!$currentPos) {
                // Start at the first stop
                $nextStop = $stops->first();
                $currentLat = $nextStop->latitude;
                $currentLng = $nextStop->longitude;
            } else {
                $currentLat = $currentPos->latitude;
                $currentLng = $currentPos->longitude;

                // Find nearest stop
                $haversine = "(6371 * acos(cos(radians({$currentLat})) * cos(radians(latitude)) * cos(radians(longitude) - radians({$currentLng})) + sin(radians({$currentLat})) * sin(radians(latitude))))";
                
                $nearestStop = RouteStop::where('route_id', $bus->route_id)
                    ->select('*')
                    ->selectRaw("$haversine AS distance")
                    ->orderBy('distance')
                    ->first();

                if ($nearestStop) {
                    if ($nearestStop->distance > 1.0) { // More than 1km away
                        // Far from route, head to nearest stop to get back on track
                        $nextStop = $nearestStop;
                    } else {
                        // On or near route, head to the NEXT stop in sequence
                        $nextOrder = $nearestStop->order + 1;
                        if ($nextOrder > $stops->max('order')) {
                            $nextOrder = $stops->min('order');
                        }
                        $nextStop = $stops->where('order', $nextOrder)->first() ?? $stops->first();
                    }
                } else {
                    $nextStop = $stops->first();
                }
            }

            if ($nextStop) {
                // Move 10-20% of the distance towards the next stop
                $moveFactor = rand(10, 20) / 100;
                
                $newLat = $currentLat + ($nextStop->latitude - $currentLat) * $moveFactor;
                $newLng = $currentLng + ($nextStop->longitude - $currentLng) * $moveFactor;

                // Add some jitter (approx 5-10 meters)
                $newLat += (rand(-10, 10) / 100000);
                $newLng += (rand(-10, 10) / 100000);

                $bus->locations()->create([
                    'latitude' => $newLat,
                    'longitude' => $newLng,
                    'recorded_at' => now(),
                ]);

                // Also update the Bus model's lat/lng for easy access
                $bus->update([
                    'latitude' => $newLat,
                    'longitude' => $newLng
                ]);
            }
        }

        $this->info('Bus locations updated successfully.');
    }
}
