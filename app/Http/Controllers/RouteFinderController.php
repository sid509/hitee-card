<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Models\RouteStop;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class RouteFinderController extends Controller
{
    /**
     * Display the route finder page and handle search logic.
     */
    public function index(Request $request)
    {
        $from = $request->get('from');
        $to = $request->get('to');

        $directRoutes = collect();
        $connectedRoutes = collect();

        if ($from && $to && $from !== $to) {
            // 1. Find Direct Routes
            $directRoutes = $this->findDirectRoutes($from, $to);

            // 2. Find Connected Routes (only if needed or for alternatives)
            $connectedRoutes = $this->findConnectedRoutes($from, $to, $directRoutes->pluck('id')->toArray());
        }

        return view('modules.route_finder.index', compact('from', 'to', 'directRoutes', 'connectedRoutes'));
    }

    /**
     * Find routes that cover both stops directly in the correct order.
     */
    private function findDirectRoutes($from, $to): Collection
    {
        $routes = Route::whereHas('stops', fn($q) => $q->where('stop_name', $from))
            ->whereHas('stops', fn($q) => $q->where('stop_name', $to))
            ->with(['stops', 'buses.merchant'])
            ->get();

        $validRoutes = collect();

        foreach ($routes as $route) {
            $stops = $route->stops->pluck('stop_name')->toArray();
            $fromIdx = array_search($from, $stops);
            $toIdx = array_search($to, $stops);

            if ($fromIdx !== false && $toIdx !== false && $fromIdx < $toIdx) {
                $validRoutes->push($route);
            }
        }

        return $validRoutes;
    }

    /**
     * Find routes requiring one transfer.
     */
    private function findConnectedRoutes($from, $to, array $excludeDirectRouteIds): Collection
    {
        // Get all routes passing through 'from'
        $startingRoutes = Route::whereHas('stops', fn($q) => $q->where('stop_name', $from))
            ->with(['stops', 'buses.merchant'])
            ->get();

        // Get all routes passing through 'to'
        $endingRoutes = Route::whereHas('stops', fn($q) => $q->where('stop_name', $to))
            ->with(['stops', 'buses.merchant'])
            ->get();

        $connections = collect();

        foreach ($startingRoutes as $r1) {
            $r1Stops = $r1->stops->pluck('stop_name')->toArray();
            $fromIdx = array_search($from, $r1Stops);

            foreach ($endingRoutes as $r2) {
                // Skip if same route or already found as direct
                if ($r1->id === $r2->id || in_array($r1->id, $excludeDirectRouteIds)) continue;

                $r2Stops = $r2->stops->pluck('stop_name')->toArray();
                $toIdx = array_search($to, $r2Stops);

                // Find intersection stops
                $commonStops = array_intersect($r1Stops, $r2Stops);

                foreach ($commonStops as $sName) {
                    $sIdxInR1 = array_search($sName, $r1Stops);
                    $sIdxInR2 = array_search($sName, $r2Stops);

                    // Sequence validation:
                    // In R1: From -> Connection
                    // In R2: Connection -> To
                    if ($fromIdx < $sIdxInR1 && $sIdxInR2 < $toIdx) {
                        $connections->push([
                            'connection_stop' => $sName,
                            'leg1' => $r1,
                            'leg2' => $r2
                        ]);
                        // We found at least one connection for this route pair, 
                        // move to next pair to avoid too many duplicates of same route pairs
                        break; 
                    }
                }
            }
        }

        return $connections;
    }
}
