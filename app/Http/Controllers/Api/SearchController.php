<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bus;
use App\Models\Parking;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Find nearby buses within a radius.
     * 
     * Expects headers: 
     * - X-User-Lat: Latitude
     * - X-User-Lon: Longitude
     * - X-Search-Radius: Radius in km (optional, defaults to 5)
     */
    public function nearbyBuses(Request $request)
    {
        $lat = $request->header('X-User-Lat');
        $lng = $request->header('X-User-Lon') ?? $request->header('X-User-Lng');
        $radius = $request->header('X-Search-Radius', 5);

        if (!$lat || !$lng) {
            return apiResponse(false, 'Latitude (X-User-Lat) and Longitude (X-User-Lon) headers are required', '', 400);
        }

        $haversine = "(6371 * acos(cos(radians($lat)) * cos(radians(latitude)) * cos(radians(longitude) - radians($lng)) + sin(radians($lat)) * sin(radians(latitude))))";

        $buses = Bus::select(['id', 'name', 'bus_number', 'latitude', 'longitude'])
            ->selectRaw("$haversine AS distance")
            ->having("distance", "<=", $radius)
            ->orderBy("distance")
            ->get();

        return apiResponse(true, 'Nearby buses fetched successfully', $buses);
    }

    /**
     * Find nearby parkings within a radius.
     * 
     * Expects headers: 
     * - X-User-Lat: Latitude
     * - X-User-Lon: Longitude
     * - X-Search-Radius: Radius in km (optional, defaults to 5)
     */
    public function nearbyParkings(Request $request)
    {
        $lat = $request->header('X-User-Lat');
        $lng = $request->header('X-User-Lon') ?? $request->header('X-User-Lng');
        $radius = $request->header('X-Search-Radius', 5);

        if (!$lat || !$lng) {
            return apiResponse(false, 'Latitude (X-User-Lat) and Longitude (X-User-Lon) headers are required', '', 400);
        }

        $haversine = "(6371 * acos(cos(radians($lat)) * cos(radians(latitude)) * cos(radians(longitude) - radians($lng)) + sin(radians($lat)) * sin(radians(latitude))))";

        $parkings = Parking::select(['id', 'name', 'location', 'latitude', 'longitude'])
            ->selectRaw("$haversine AS distance")
            ->having("distance", "<=", $radius)
            ->orderBy("distance")
            ->get();

        return apiResponse(true, 'Nearby parkings fetched successfully', $parkings);
    }
}
