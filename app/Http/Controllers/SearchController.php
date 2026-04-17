<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Bus;
use App\Models\Parking;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Search users for Select2 AJAX.
     */
    public function users(Request $request)
    {
        $search = $request->get('q');
        $query = User::query();

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%$search%")
                  ->orWhere('email', 'LIKE', "%$search%");
            });
        }

        $users = $query->paginate(10);

        return response()->json([
            'results' => collect($users->items())->map(function ($user) {
                return [
                    'id' => $user->id,
                    'text' => $user->name . " (" . $user->email . ") - Bal: Rs. " . number_format($user->balance(), 2),
                    'balance' => $user->balance()
                ];
            })->toArray(),
            'pagination' => [
                'more' => $users->hasMorePages()
            ]
        ]);
    }

    /**
     * Search merchants for Select2 AJAX.
     */
    public function merchants(Request $request)
    {
        $search = $request->get('q');
        $query = User::whereHas('roles', fn($q) => $q->where('slug', 'merchant'));

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%$search%")
                  ->orWhere('email', 'LIKE', "%$search%");
            });
        }

        $merchants = $query->paginate(10);

        return response()->json([
            'results' => collect($merchants->items())->map(function ($merchant) {
                return [
                    'id' => $merchant->id,
                    'text' => $merchant->name . " (" . $merchant->email . ")"
                ];
            })->toArray(),
            'pagination' => [
                'more' => $merchants->hasMorePages()
            ]
        ]);
    }

    /**
     * Search reference items (Bus/Parking) for Select2 AJAX.
     */
    public function references(Request $request)
    {
        $type = $request->get('type'); // fare_deduction or parking
        $merchantId = $request->get('merchant_id');
        $search = $request->get('q');

        if ($type === 'fare_deduction') {
            $query = Bus::query();
            if ($merchantId) $query->where('merchant_id', $merchantId);
            if ($search) $query->where('name', 'LIKE', "%$search%")->orWhere('bus_number', 'LIKE', "%$search%");
            
            $items = $query->paginate(10);
            return response()->json([
                'results' => collect($items->items())->map(fn($item) => ['id' => $item->id, 'text' => $item->name . " (" . $item->bus_number . ")"])->toArray(),
                'pagination' => ['more' => $items->hasMorePages()]
            ]);
        } elseif ($type === 'parking') {
            $query = Parking::query();
            if ($merchantId) $query->where('merchant_id', $merchantId);
            if ($search) $query->where('name', 'LIKE', "%$search%")->orWhere('location', 'LIKE', "%$search%");
            
            $items = $query->paginate(10);
            return response()->json([
                'results' => collect($items->items())->map(fn($item) => ['id' => $item->id, 'text' => $item->name . " (" . $item->location . ")"])->toArray(),
                'pagination' => ['more' => $items->hasMorePages()]
            ]);
        }

        return response()->json(['results' => []]);
    }

    /**
     * Find nearby assets within 5km for a given lat/lng.
     */
    public function nearby(Request $request)
    {
        $lat = $request->get('lat');
        $lng = $request->get('lng');
        $radius = 5; // km

        if (!$lat || !$lng) {
            return response()->json(['error' => 'Coordinates required'], 400);
        }

        // Haversine formula
        $haversine = "(6371 * acos(cos(radians($lat)) * cos(radians(latitude)) * cos(radians(longitude) - radians($lng)) + sin(radians($lat)) * sin(radians(latitude))))";

        $buses = Bus::select(['id', 'name', 'bus_number', 'latitude', 'longitude'])
            ->selectRaw("$haversine AS distance")
            ->having("distance", "<=", $radius)
            ->orderBy("distance")
            ->get();

        $parkings = Parking::select(['id', 'name', 'location', 'latitude', 'longitude'])
            ->selectRaw("$haversine AS distance")
            ->having("distance", "<=", $radius)
            ->orderBy("distance")
            ->get();

        return response()->json([
            'buses' => $buses,
            'parkings' => $parkings,
            'counts' => [
                'buses' => $buses->count(),
                'parkings' => $parkings->count()
            ]
        ]);
    }
}
