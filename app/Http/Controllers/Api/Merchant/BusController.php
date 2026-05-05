<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Bus;
use Illuminate\Http\Request;

/**
 * @group MerchantApi 
 * @subgroup Bus
 */
class BusController extends Controller
{
    /**
     * List Merchant Buses
     */
    public function index(Request $request)
    {
        $merchant = $request->user();

        $query = Bus::where('merchant_id', $merchant->id)
            ->with(['route', 'currentPosition']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('bus_number', 'like', "%{$request->search}%");
            });
        }

        if ($request->filled('route_id')) {
            $query->where('route_id', $request->route_id);
        }

        $buses = $query->get()->map(function($bus) {
            $isMoving = false;
            if ($bus->currentPosition) {
                $isMoving = $bus->currentPosition->speed > 0 && 
                            $bus->currentPosition->recorded_at && 
                            $bus->currentPosition->recorded_at->diffInMinutes(now()) < 5;
            }

            return [
                'id' => $bus->id,
                'name' => $bus->name,
                'bus_number' => $bus->bus_number,
                'status' => $bus->status,
                'is_moving' => $isMoving,
                'route' => $bus->route ? [
                    'id' => $bus->route->id,
                    'name' => $bus->route->name
                ] : null,
                'current_position' => $bus->currentPosition ? [
                    'latitude' => $bus->currentPosition->latitude,
                    'longitude' => $bus->currentPosition->longitude,
                    'heading' => $bus->currentPosition->heading,
                    'speed' => $bus->currentPosition->speed,
                    'recorded_at' => $bus->currentPosition->recorded_at->toDateTimeString()
                ] : null,
                'image_url' => $bus->featured_image_url
            ];
        });

        if ($request->has('is_moving')) {
            $isMovingFilter = filter_var($request->is_moving, FILTER_VALIDATE_BOOLEAN);
            $buses = $buses->filter(fn($b) => $b['is_moving'] === $isMovingFilter)->values();
        }

        return apiResponse(true, 'Buses fetched successfully', $buses);
    }

    /**
     * Show Bus Details
     */
    public function show(Request $request, $id)
    {
        $bus = Bus::with([
            'route.stops', 
            'currentPosition', 
            'activeFare.matrices.fromStop',
            'activeFare.matrices.toStop'
        ])->withCount('ongoingRides')->findOrFail($id);

        if ($bus->merchant_id !== $request->user()->id) {
            return apiResponse(false, 'You do not have permission to view this bus', null, 403);
        }

        $data = [
            'id'          => $bus->id,
            'name'        => $bus->name,
            'bus_number'  => $bus->bus_number,
            'total_capacity' => (int) $bus->total_capacity,
            'current_occupancy' => (int) $bus->ongoing_rides_count,
            'status'      => $bus->status,
            'latitude'    => $bus->latitude,
            'longitude'   => $bus->longitude,
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
            'current_position' => $bus->currentPosition ? [
                'latitude' => $bus->currentPosition->latitude,
                'longitude' => $bus->currentPosition->longitude,
                'heading' => $bus->currentPosition->heading,
                'speed' => $bus->currentPosition->speed,
                'recorded_at' => $bus->currentPosition->recorded_at->toDateTimeString()
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
}
