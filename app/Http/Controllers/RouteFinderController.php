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
        $fromId = $request->get('from');
        $toId = $request->get('to');

        $fromStop = $fromId ? \App\Models\Stop::find($fromId) : null;
        $toStop = $toId ? \App\Models\Stop::find($toId) : null;

        $from = $fromStop ? $fromStop->name : null;
        $to = $toStop ? $toStop->name : null;

        $directRoutes = collect();
        $connectedRoutes = collect();

        if ($fromStop && $toStop && $fromId !== $toId) {
            // 1. Find Direct Routes
            $directRoutes = $this->findDirectRoutes($fromId, $toId);

            // 2. Find Connected Routes
            $connectedRoutes = $this->findConnectedRoutes($fromId, $toId, $directRoutes->pluck('id')->toArray());
        }

        return view('modules.route_finder.index', compact('from', 'to', 'fromId', 'toId', 'directRoutes', 'connectedRoutes'));
    }

    /**
     * Find routes that cover both stops directly in the correct order.
     */
    private function findDirectRoutes($fromId, $toId): Collection
    {
        $routes = Route::whereHas('stops', fn($q) => $q->where('stop_id', $fromId))
            ->whereHas('stops', fn($q) => $q->where('stop_id', $toId))
            ->with(['stops', 'buses.merchant'])
            ->get();

        $validRoutes = collect();

        foreach ($routes as $route) {
            $stops = $route->stops->pluck('stop_id')->toArray();
            $fromIdx = array_search($fromId, $stops);
            $toIdx = array_search($toId, $stops);

            if ($fromIdx !== false && $toIdx !== false && $fromIdx < $toIdx) {
                $validRoutes->push($route);
            }
        }

        return $validRoutes;
    }

    /**
     * Find routes requiring one transfer.
     */
    private function findConnectedRoutes($fromId, $toId, array $excludeDirectRouteIds): Collection
    {
        // Get all routes passing through 'from'
        $startingRoutes = Route::whereHas('stops', fn($q) => $q->where('stop_id', $fromId))
            ->with(['stops', 'buses.merchant'])
            ->get();

        // Get all routes passing through 'to'
        $endingRoutes = Route::whereHas('stops', fn($q) => $q->where('stop_id', $toId))
            ->with(['stops', 'buses.merchant'])
            ->get();

        $connections = collect();

        foreach ($startingRoutes as $r1) {
            $r1Stops = $r1->stops->pluck('stop_id')->toArray();
            $fromIdx = array_search($fromId, $r1Stops);

            foreach ($endingRoutes as $r2) {
                // Skip if same route or already found as direct
                if ($r1->id === $r2->id || in_array($r1->id, $excludeDirectRouteIds)) continue;

                $r2Stops = $r2->stops->pluck('stop_id')->toArray();
                $toIdx = array_search($toId, $r2Stops);

                // Find intersection stops (using stop_id)
                $commonStopIds = array_intersect($r1Stops, $r2Stops);

                foreach ($commonStopIds as $stopId) {
                    $sIdxInR1 = array_search($stopId, $r1Stops);
                    $sIdxInR2 = array_search($stopId, $r2Stops);

                    // Sequence validation:
                    // In R1: From -> Connection
                    // In R2: Connection -> To
                    if ($fromIdx < $sIdxInR1 && $sIdxInR2 < $toIdx) {
                        $stop = \App\Models\Stop::find($stopId);
                        $connections->push([
                            'connection_stop' => $stop->name,
                            'leg1' => $r1,
                            'leg2' => $r2
                        ]);
                        break; 
                    }
                }
            }
        }

        return $connections;
    }
}
