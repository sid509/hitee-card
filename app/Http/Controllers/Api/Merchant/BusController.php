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
        $bus = Bus::where('merchant_id', $request->user()->id)
            ->with(['route', 'currentPosition', 'active_fare'])
            ->findOrFail($id);

        return apiResponse(true, 'Bus details fetched successfully', $bus);
    }
}
