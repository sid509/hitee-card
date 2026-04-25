<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bus;
use App\Models\Parking;
use Illuminate\Http\Request;

/**
 * @group Homepage
 *
 * APIs for the mobile app's main dashboard, including buses and parkings.
 */
class HomepageController extends Controller
{
    // ──────────────────────────────────────────────
    // LIST ENDPOINTS (search + pagination)
    // ──────────────────────────────────────────────

    /**
     * GET /api/buses
     *
     * Returns a searchable, paginated list of active buses for the homepage.
     * Proximity sorting/filtering is applied only if both latitude and longitude are provided.
     *
     * @queryParam search string (optional) Search by name or bus number. Example: KTM-01
     * @queryParam latitude float (optional) Latitude for proximity search.
     * @queryParam longitude float (optional) Longitude for proximity search.
     * @queryParam radius float (optional) Search radius in km. Defaults to 10.
     * @queryParam perPage integer (optional) Results per page. Defaults to 2.
     * @queryParam page integer (optional) Page number for pagination.
     */
    public function listBuses(Request $request)
    {
        $request->validate([
            'search'    => 'nullable|string',
            'latitude'  => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'radius'    => 'nullable|numeric',
            'perPage'   => 'nullable|integer|min:1|max:100',
            'page'      => 'nullable|integer|min:1',
        ]);

        $perPage = (int) $request->get('perPage', 2);
        $perPage = max(1, min($perPage, 100));

        $query = Bus::with(['merchant:id,name', 'route:id,name,direction'])
            ->where('status', 'active');

        // Search by name or bus_number
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('bus_number', 'like', "%{$search}%")
            );
        }

        // Proximity filter/sort
        $lat = $request->get('latitude') ?? $request->get('lat');
        $lng = $request->get('longitude') ?? $request->get('lon') ?? $request->get('lng');

        if ($lat && $lng) {
            $haversine = "(6371 * acos(cos(radians({$lat})) * cos(radians(latitude)) * cos(radians(longitude) - radians({$lng})) + sin(radians({$lat})) * sin(radians(latitude))))";
            $radius = (float) $request->get('radius', 10);
            $query->selectRaw("*, {$haversine} AS distance")
                  ->having('distance', '<=', $radius)
                  ->orderBy('distance');
        } else {
            $query->select('*')->orderBy('name');
        }

        $buses = $query->paginate($perPage);

        $mapped = collect($buses->items())->map(fn($bus) => [
            'id'          => $bus->id,
            'name'        => $bus->name,
            'bus_number'  => $bus->bus_number,
            'status'      => $bus->status,
            'latitude'    => $bus->latitude,
            'longitude'   => $bus->longitude,
            'distance_km' => isset($bus->distance) ? round((float) $bus->distance, 2) : null,
            'merchant'    => $bus->merchant?->name,
            'route'       => $bus->route ? [
                'id'        => $bus->route->id,
                'name'      => $bus->route->name,
                'direction' => $bus->route->direction,
            ] : null,
            'image_url'   => $bus->featured_image_url,
        ]);

        return response()->json([
            'status'   => true,
            'message'  => 'Buses fetched successfully',
            'meta'     => [],
            'paginate' => [
                'total'        => $buses->total(),
                'per_page'     => $buses->perPage(),
                'current_page' => $buses->currentPage(),
                'last_page'    => $buses->lastPage(),
            ],
            'content'  => $mapped,
        ]);
    }

    /**
     * GET /api/parkings
     *
     * Returns a searchable, paginated list of active parking lots.
     * Proximity sorting/filtering is applied only if both latitude and longitude are provided.
     *
     * @queryParam search string (optional) Search by name or location. Example: Durbar
     * @queryParam latitude float (optional) Latitude for proximity search.
     * @queryParam longitude float (optional) Longitude for proximity search.
     * @queryParam radius float (optional) Search radius in km. Defaults to 10.
     * @queryParam perPage integer (optional) Results per page. Defaults to 2.
     * @queryParam page integer (optional) Page number for pagination.
     */
    public function listParkings(Request $request)
    {
        $request->validate([
            'search'    => 'nullable|string',
            'latitude'  => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'radius'    => 'nullable|numeric',
            'perPage'   => 'nullable|integer|min:1|max:100',
            'page'      => 'nullable|integer|min:1',
        ]);

        $perPage = (int) $request->get('perPage', 2);
        $perPage = max(1, min($perPage, 100));

        $query = Parking::with(['merchant:id,name', 'attributes:id,name,icon', 'media'])
            ->where('status', 'opened');

        // Search by name or location
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('location', 'like', "%{$search}%")
            );
        }

        // Proximity filter/sort
        $lat = $request->get('latitude') ?? $request->get('lat');
        $lng = $request->get('longitude') ?? $request->get('lon') ?? $request->get('lng');

        if ($lat && $lng) {
            $haversine = "(6371 * acos(cos(radians({$lat})) * cos(radians(latitude)) * cos(radians(longitude) - radians({$lng})) + sin(radians({$lat})) * sin(radians(latitude))))";
            $radius = (float) $request->get('radius', 10);
            $query->selectRaw("*, {$haversine} AS distance")
                  ->having('distance', '<=', $radius)
                  ->orderBy('distance');
        } else {
            $query->select('*')->orderBy('name');
        }

        $parkings = $query->paginate($perPage);

        $mapped = collect($parkings->items())->map(fn($p) => [
            'id'               => $p->id,
            'name'             => $p->name,
            'location'         => $p->location,
            'status'           => $p->status,
            'latitude'         => $p->latitude,
            'longitude'        => $p->longitude,
            'distance_km'      => isset($p->distance) ? round((float) $p->distance, 2) : null,
            'first_hour_pts'   => (float) $p->first_hour_fee,    // pts, not Rs.
            'onwards_hour_pts' => (float) $p->onwards_hour_fee,  // pts, not Rs.
            'merchant'         => $p->merchant?->name,
            'attributes'       => $p->attributes->map(fn($a) => [
                'name' => $a->name,
                'icon' => $a->icon_url,
            ]),
            'image_url'        => $p->featured_image_url,
            'gallery'          => $p->media->where('collection_name', 'gallery')->map(fn($m) => [
                'id'  => $m->id,
                'url' => $m->url,
            ])->values(),
        ]);

        return response()->json([
            'status'   => true,
            'message'  => 'Parkings fetched successfully',
            'meta'     => ['disclaimer' => 'Parking fees are charged in Hitee Points (pts). 1 Rs = 1 pt.'],
            'paginate' => [
                'total'        => $parkings->total(),
                'per_page'     => $parkings->perPage(),
                'current_page' => $parkings->currentPage(),
                'last_page'    => $parkings->lastPage(),
            ],
            'content'  => $mapped,
        ]);
    }

    /**
     * GET /api/buses/{id}
     */
    public function showBus($id)
    {
        $bus = Bus::with([
            'merchant:id,name', 
            'route.stops', 
            'activeFare.matrices.fromStop',
            'activeFare.matrices.toStop'
        ])->find($id);

        if (!$bus) {
            return apiResponse(false, 'Bus not found', '', 404);
        }

        $data = [
            'id'          => $bus->id,
            'name'        => $bus->name,
            'bus_number'  => $bus->bus_number,
            'status'      => $bus->status,
            'latitude'    => $bus->latitude,
            'longitude'   => $bus->longitude,
            'merchant'    => $bus->merchant?->name,
            'image_url'   => $bus->featured_image_url,
            'route'       => $bus->route ? [
                'id'        => $bus->route->id,
                'name'      => $bus->route->name,
                'direction' => $bus->route->direction,
                'stops'     => $bus->route->stops->map(fn($s) => [
                    'id'        => $s->id,
                    'name'      => $s->stop_name,
                    'latitude'  => $s->latitude,
                    'longitude' => $s->longitude,
                    'order'     => $s->order,
                ]),
            ] : null,
            'active_fare' => $bus->activeFare ? [
                'id'             => $bus->activeFare->id,
                'name'           => $bus->activeFare->name,
                'effective_from' => $bus->activeFare->effective_from,
                'matrix'         => $bus->activeFare->matrices->map(fn($m) => [
                    'from'   => $m->fromStop?->stop_name,
                    'to'     => $m->toStop?->stop_name,
                    'amount' => (float) $m->amount,
                ]),
            ] : null,
        ];

        return apiResponse(true, 'Bus details fetched successfully', $data);
    }

    /**
     * GET /api/parkings/{id}
     */
    public function showParking($id)
    {
        $parking = Parking::with(['merchant:id,name', 'attributes:id,name,icon', 'media'])->find($id);

        if (!$parking) {
            return apiResponse(false, 'Parking not found', '', 404);
        }

        $data = [
            'id'               => $parking->id,
            'name'             => $parking->name,
            'location'         => $parking->location,
            'status'           => $parking->status,
            'latitude'         => $parking->latitude,
            'longitude'        => $parking->longitude,
            'first_hour_pts'   => (float) $parking->first_hour_fee,
            'onwards_hour_pts' => (float) $parking->onwards_hour_fee,
            'merchant'         => $parking->merchant?->name,
            'attributes'       => $parking->attributes->map(fn($a) => [
                'name' => $a->name,
                'icon' => $a->icon_url,
            ]),
            'image_url'        => $parking->featured_image_url,
            'gallery'          => $parking->media->where('collection_name', 'gallery')->map(fn($m) => [
                'id'  => $m->id,
                'url' => $m->url,
            ])->values(),
        ];

        return apiResponse(true, 'Parking details fetched successfully', $data);
    }
}
