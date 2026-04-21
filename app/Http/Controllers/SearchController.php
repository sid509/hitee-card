<?php

namespace App\Http\Controllers;

use App\Models\RouteStop;
use App\Models\User;
use App\Models\Bus;
use App\Models\Parking;
use App\Models\Route as RouteModel;
use App\Models\Card;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Optimized Global Search (Spotlight Style)
     */
    public function global(Request $request)
    {
        $q = $request->get('q');
        if (!$q || strlen($q) < 2) return response()->json([]);

        $user = auth()->user();
        $results = [];

        // 1. Search Users (Admin Only)
        if ($user->hasRole('super-admin')) {
            $users = User::where(function($query) use ($q) {
                    $query->where('name', 'LIKE', "%$q%")
                          ->orWhere('email', 'LIKE', "%$q%")
                          ->orWhere('phone_number', 'LIKE', "%$q%");
                })
                ->limit(5)->get();
            
            if ($users->count() > 0) {
                $results['Users'] = $users->map(fn($u) => [
                    'title' => $u->name,
                    'subtitle' => $u->phone_number ?? $u->email,
                    'url' => route('users.show', $u->id),
                    'icon' => 'bx-user'
                ]);
            }
        }

        // 2. Search Buses
        $busesQuery = Bus::where(function($query) use ($q) {
            $query->where('name', 'LIKE', "%$q%")
                  ->orWhere('bus_number', 'LIKE', "%$q%")
                  ->orWhere('hwid', 'LIKE', "%$q%");
        });

        if ($user->hasRole('merchant')) {
            $busesQuery->where('merchant_id', $user->id);
        }

        $buses = $busesQuery->limit(5)->get();
        if ($buses->count() > 0) {
            $results['Buses'] = $buses->map(fn($b) => [
                'title' => $b->bus_number,
                'subtitle' => $b->name,
                'url' => route('buses.show', $b->id),
                'icon' => 'bx-bus'
            ]);
        }

        // 3. Search Parkings
        $parkingsQuery = Parking::where(function($query) use ($q) {
            $query->where('name', 'LIKE', "%$q%")
                  ->orWhere('location', 'LIKE', "%$q%");
        });

        if ($user->hasRole('merchant')) {
            $parkingsQuery->where('merchant_id', $user->id);
        }

        $parkings = $parkingsQuery->limit(5)->get();
        if ($parkings->count() > 0) {
            $results['Parkings'] = $parkings->map(fn($p) => [
                'title' => $p->name,
                'subtitle' => $p->location,
                'url' => route('parkings.show', $p->id),
                'icon' => 'bx-map-pin'
            ]);
        }

        // 4. Search Routes
        $routes = RouteModel::where('name', 'LIKE', "%$q%")->limit(5)->get();
        if ($routes->count() > 0) {
            $results['Routes'] = $routes->map(fn($r) => [
                'title' => $r->name,
                'subtitle' => 'Transit Route',
                'url' => route('routes.show', $r->id),
                'icon' => 'bx-git-commit'
            ]);
        }

        // 5. Search Cards (Admin Only)
        if ($user->hasRole('super-admin')) {
            $cards = Card::where('card_number', 'LIKE', "%$q%")
                ->orWhere('hwid', 'LIKE', "%$q%")
                ->limit(5)->get();

            if ($cards->count() > 0) {
                $results['Cards'] = $cards->map(fn($c) => [
                    'title' => $c->card_number,
                    'subtitle' => 'Hardware ID: ' . $c->hwid,
                    'url' => route('cards.show', $c->id),
                    'icon' => 'bx-credit-card'
                ]);
            }
        }

        return response()->json($results);
    }

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
                  ->orWhere('email', 'LIKE', "%$search%")
                  ->orWhere('phone_number', 'LIKE', "%$search%");
            });
        }

        $users = $query->paginate(10);

        return response()->json([
            'results' => collect($users->items())->map(function ($user) {
                return [
                    'id' => $user->id,
                    'text' => $user->name . " (" . ($user->phone_number ?? $user->email) . ") - Bal: Rs. " . number_format($user->balance(), 2),
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
        $query = User::whereHas('roles', fn($q) => $q->whereIn('slug', ['merchant', 'staff']));

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%$search%")
                  ->orWhere('email', 'LIKE', "%$search%")
                  ->orWhere('phone_number', 'LIKE', "%$search%");
            });
        }

        $merchants = $query->paginate(10);

        return response()->json([
            'results' => collect($merchants->items())->map(function ($merchant) {
                return [
                    'id' => $merchant->id,
                    'text' => $merchant->name . " (" . ($merchant->phone_number ?? $merchant->email) . ")"
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
        $user = auth()->user();

        if ($type === 'fare_deduction') {
            $query = Bus::query();
            if ($merchantId) {
                $query->where('merchant_id', $merchantId);
            } elseif ($user->hasRole('merchant')) {
                $query->where('merchant_id', $user->id);
            } elseif ($user->hasRole('staff')) {
                $query->whereIn('id', $user->assignedBuses->pluck('id'));
            }

            if ($search) $query->where('name', 'LIKE', "%$search%")->orWhere('bus_number', 'LIKE', "%$search%");

            $items = $query->paginate(10);
            return response()->json([
                'results' => collect($items->items())->map(fn($item) => ['id' => $item->id, 'text' => $item->name . " (" . $item->bus_number . ")"])->toArray(),
                'pagination' => ['more' => $items->hasMorePages()]
            ]);
        } elseif ($type === 'parking') {
            $query = Parking::query();
            if ($merchantId) {
                $query->where('merchant_id', $merchantId);
            } elseif ($user->hasRole('merchant')) {
                $query->where('merchant_id', $user->id);
            } elseif ($user->hasRole('staff')) {
                $query->whereIn('id', $user->assignedParkings->pluck('id'));
            }

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
     * Search global stops for Select2 AJAX.
     */
    public function stops(Request $request)
    {
        $search = $request->get('q');
        $query = \App\Models\Stop::query();

        if ($search) {
            $query->where('name', 'LIKE', "%$search%");
        }

        $stops = $query->paginate(10);

        return response()->json([
            'results' => collect($stops->items())->map(fn($s) => [
                'id' => $s->id, 
                'text' => $s->name,
                'lat' => $s->latitude,
                'lng' => $s->longitude
            ])->toArray(),
            'pagination' => ['more' => $stops->hasMorePages()]
        ]);
    }

    /**
     * Find buses covering a specific from/to stop pair.
     */
    public function findBuses(Request $request)
    {
        $from = $request->get('from');
        $to = $request->get('to');

        if (!$from || !$to) return response()->json(['buses' => []]);

        $routes = RouteModel::whereHas('stops', function($q) use ($from) {
            $q->where('stop_name', $from);
        })->whereHas('stops', function($q) use ($to) {
            $q->where('stop_name', $to);
        })->with(['buses.merchant', 'stops'])->get();

        $validBuses = collect();

        foreach ($routes as $route) {
            $stops = $route->stops->pluck('stop_name')->toArray();
            $fromIdx = array_search($from, $stops);
            $toIdx = array_search($to, $stops);

            // Ensure 'from' comes before 'to' in the ordered sequence
            if ($fromIdx !== false && $toIdx !== false && $fromIdx < $toIdx) {
                foreach ($route->buses as $bus) {
                    $validBuses->push([
                        'id' => $bus->id,
                        'name' => $bus->name,
                        'number' => $bus->bus_number,
                        'merchant' => $bus->merchant->name,
                        'route' => $route->name,
                        'fare' => $bus->activeFare ? $bus->activeFare->name : 'Standard',
                        'url' => route('buses.show', $bus->id)
                    ]);
                }
            }
        }

        return response()->json(['buses' => $validBuses->unique('id')->values()]);
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
