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
     * @queryParam lat required The latitude. Example: 27.7172
     * @queryParam lon required The longitude. Example: 85.3240
     * @queryParam radius integer The search radius in km. Defaults to 5. Example: 5
     */
    public function nearbyBuses(Request $request)
    {
        $lat = $request->get('lat');
        $lng = $request->get('lon') ?? $request->get('lng');
        $radius = $request->get('radius', 5);

        if (!$lat || !$lng) {
            return apiResponse(false, 'Latitude (lat) and Longitude (lon) are required', '', 400);
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
     * @queryParam lat required The latitude. Example: 27.7172
     * @queryParam lon required The longitude. Example: 85.3240
     * @queryParam radius integer The search radius in km. Defaults to 5. Example: 5
     */
    public function nearbyParkings(Request $request)
    {
        $lat = $request->get('lat');
        $lng = $request->get('lon') ?? $request->get('lng');
        $radius = $request->get('radius', 5);

        if (!$lat || !$lng) {
            return apiResponse(false, 'Latitude (lat) and Longitude (lon) are required', '', 400);
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
