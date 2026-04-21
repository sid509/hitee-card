<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Models\Stop;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class RouteFinderController extends Controller
{
    public function index(Request $request)
    {
        $fromId = $request->get('from');
        $toId = $request->get('to');

        $fromStop = $fromId ? Stop::find($fromId) : null;
        $toStop = $toId ? Stop::find($toId) : null;

        $from = $fromStop ? $fromStop->name : null;
        $to = $toStop ? $toStop->name : null;

        $options = collect();

        if ($fromStop && $toStop && $fromId !== $toId) {
            $options = $this->findJourneyOptions($fromId, $toId);
        }

        return view('modules.route_finder.index', compact('from', 'to', 'fromId', 'toId', 'options'));
    }

    private function findJourneyOptions($startStopId, $endStopId): Collection
    {
        $allStops = Stop::all();
        $allRoutes = Route::with(['stops', 'fares.matrices', 'buses'])->get();

        // 1. Find DIRECT paths
        $directPaths = [];
        $directRoutes = $allRoutes->filter(function($r) use ($startStopId, $endStopId) {
            $sIds = $r->stops->pluck('stop_id')->toArray();
            $idxStart = array_search($startStopId, $sIds);
            $idxEnd = array_search($endStopId, $sIds);
            return ($idxStart !== false && $idxEnd !== false && $idxStart < $idxEnd);
        });

        foreach ($directRoutes as $r) {
            $leg = [
                'route' => $r,
                'fare' => $this->getFareForLeg($r, $startStopId, $endStopId),
                'from' => Stop::find($startStopId),
                'to' => Stop::find($endStopId),
                'distance' => $this->getDistForLeg($r, $startStopId, $endStopId),
                'intermediates' => $this->getIntermediatesForLeg($r, $startStopId, $endStopId),
                'from_available_lines' => $this->getAvailableLinesForStop($allRoutes, $startStopId),
                'to_available_lines' => $this->getAvailableLinesForStop($allRoutes, $endStopId)
            ];
            $directPaths[] = [$leg];
        }

        // 2. Dijkstra with penalty for transfers
        $transferPenalty = 5.0; 
        $distances = [];
        $predecessors = []; 

        foreach ($allStops as $stop) { $distances[$stop->id] = INF; }
        $distances[$startStopId] = 0;
        
        $queue = new \SplPriorityQueue();
        $queue->insert($startStopId, 0);

        while (!$queue->isEmpty()) {
            $u = $queue->extract();
            if ($u == $endStopId) break;

            $availableRoutes = $allRoutes->filter(fn($r) => $r->stops->contains('stop_id', $u));
            foreach ($availableRoutes as $route) {
                $stops = $route->stops->values();
                $uIdx = $stops->search(fn($s) => $s->stop_id == $u);
                
                $accumulatedDist = 0;
                for ($i = $uIdx + 1; $i < $stops->count(); $i++) {
                    $segmentDist = $this->haversine($stops[$i-1]->latitude, $stops[$i-1]->longitude, $stops[$i]->latitude, $stops[$i]->longitude);
                    $accumulatedDist += $segmentDist;
                    $cost = $accumulatedDist + $transferPenalty; 
                    $alt = $distances[$u] + $cost;

                    if ($alt < $distances[$stops[$i]->stop_id]) {
                        $distances[$stops[$i]->stop_id] = $alt;
                        $predecessors[$stops[$i]->stop_id] = ['from' => $u, 'dist' => $accumulatedDist, 'route' => $route];
                        $queue->insert($stops[$i]->stop_id, -$alt);
                    }
                }
            }
        }

        // 3. Reconstruct transfer path
        $transferPath = [];
        $curr = $endStopId;
        while (isset($predecessors[$curr])) {
            $step = $predecessors[$curr];
            array_unshift($transferPath, [
                'from' => Stop::find($step['from']),
                'to' => Stop::find($curr),
                'route' => $step['route'],
                'fare' => $this->getFareForLeg($step['route'], $step['from'], $curr),
                'distance' => $step['dist'],
                'intermediates' => $this->getIntermediatesForLeg($step['route'], $step['from'], $curr),
                'from_available_lines' => $this->getAvailableLinesForStop($allRoutes, $step['from']),
                'to_available_lines' => $this->getAvailableLinesForStop($allRoutes, $curr)
            ]);
            $curr = $step['from'];
        }

        $allOptions = collect($directPaths);
        if (!empty($transferPath) && count($transferPath) > 1) {
            $allOptions->push($transferPath);
        }

        return $allOptions->unique(function($opt) {
            return collect($opt)->map(fn($leg) => $leg['route']->id)->implode('-');
        })->take(5);
    }

    private function getAvailableLinesForStop($allRoutes, $stopId) {
        return $allRoutes->filter(fn($r) => $r->stops->contains('stop_id', $stopId))
                         ->pluck('name')
                         ->unique()
                         ->take(5);
    }

    private function getIntermediatesForLeg($route, $fromId, $toId) {
        $stops = $route->stops->values();
        $startIdx = $stops->search(fn($s) => $s->stop_id == $fromId);
        $endIdx = $stops->search(fn($s) => $s->stop_id == $toId);
        
        $intermediateStops = [];
        for ($i = $startIdx + 1; $i < $endIdx; $i++) {
            $intermediateStops[] = Stop::find($stops[$i]->stop_id);
        }
        return collect($intermediateStops);
    }

    private function getFareForLeg($route, $fromId, $toId) {
        $fare = $route->fares->where('status', 'approved')->first();
        if ($fare) {
            $matrix = $fare->matrices->where('from_stop_id', $fromId)->where('to_stop_id', $toId)->first();
            return $matrix ? $matrix->amount : 0;
        }
        return 0;
    }

    private function getDistForLeg($route, $fromId, $toId) {
        $stops = $route->stops->values();
        $startIdx = $stops->search(fn($s) => $s->stop_id == $fromId);
        $endIdx = $stops->search(fn($s) => $s->stop_id == $toId);
        $dist = 0;
        for ($i = $startIdx + 1; $i <= $endIdx; $i++) {
            $dist += $this->haversine($stops[$i-1]->latitude, $stops[$i-1]->longitude, $stops[$i]->latitude, $stops[$i]->longitude);
        }
        return $dist;
    }

    private function haversine($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
