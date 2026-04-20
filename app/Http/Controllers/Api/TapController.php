<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bus;
use App\Models\Parking;
use App\Models\Card;
use App\Models\Ride;
use App\Models\Tap;
use App\Models\RouteStop;
use App\Models\FareMatrix;
use App\Models\BalanceOut;
use App\Models\MerchantIncome;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TapController extends Controller
{
    /**
     * Unified Tap Handler (Intelligently detects IN/OUT)
     * 
     * Body: lat, lon, card_number, hw_id
     */
    public function processTap(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lon' => 'required|numeric',
            'card_number' => 'required|exists:cards,card_number',
            'hw_id' => 'required|string'
        ]);

        $card = Card::where('card_number', $request->card_number)->with('user')->firstOrFail();
        $asset = $this->resolveAsset($request->hw_id);

        if (!$asset) return apiResponse(false, 'Scanner (Asset) not found', '', 404);

        $user = $card->user;
        if (!$user) return apiResponse(false, 'Card is not assigned to a user', '', 400);
        if ($card->status !== 'active') return apiResponse(false, 'Card is blocked or inactive', '', 403);

        // Check for an ongoing journey for this card on ANY asset
        $ongoingRide = Ride::where('card_id', $card->id)
            ->where('status', 'ongoing')
            ->first();

        return DB::transaction(function() use ($request, $user, $card, $asset, $ongoingRide) {
            if ($ongoingRide) {
                // If already has an ongoing journey -> TAP OUT
                return $this->handleTapOut($request, $ongoingRide, $asset);
            } else {
                // No ongoing journey -> TAP IN
                return $this->handleTapIn($request, $user, $card, $asset);
            }
        });
    }

    /**
     * Internal Logic: Process a Tap In event
     */
    private function handleTapIn($request, $user, $card, $asset)
    {
        // Check minimum wallet balance to start journey
        if ($user->balance() < 20) {
            return apiResponse(false, 'Insufficient balance (Min Rs. 20 required)', '', 402);
        }

        // Normalize Location (find nearest stop name)
        $location = $this->resolveLocation($request->lat, $request->lon, $asset);

        // 1. Record Raw Tap
        $tap = Tap::create([
            'user_id' => $user->id,
            'card_id' => $card->id,
            'merchant_id' => $asset->merchant_id,
            'reference_id' => $asset->id,
            'reference_type' => get_class($asset),
            'type' => 'in',
            'stop_id' => $location['id'],
            'resolved_location_name' => $location['name'],
            'latitude' => $request->lat,
            'longitude' => $request->lon
        ]);

        // 2. Create Reconciled Ride
        $ride = Ride::create([
            'user_id' => $user->id,
            'card_id' => $card->id,
            'merchant_id' => $asset->merchant_id,
            'reference_id' => $asset->id,
            'reference_type' => get_class($asset),
            'tap_in_id' => $tap->id,
            'status' => 'ongoing'
        ]);

        logActivity('tap_in', "Tapped in at {$location['name']} on {$asset->name}", ['ride_id' => $ride->id], $user->id);

        return apiResponse(true, "Tap In successful at {$location['name']}", [
            'type' => 'in',
            'ride_id' => $ride->id,
            'location' => $location['name']
        ]);
    }

    /**
     * Internal Logic: Process a Tap Out event
     */
    private function handleTapOut($request, $ride, $currentAsset)
    {
        $user = $ride->user;
        $location = $this->resolveLocation($request->lat, $request->lon, $currentAsset);

        // 1. Record Raw Tap
        $tapOut = Tap::create([
            'user_id' => $user->id,
            'card_id' => $ride->card_id,
            'merchant_id' => $currentAsset->merchant_id,
            'reference_id' => $currentAsset->id,
            'reference_type' => get_class($currentAsset),
            'type' => 'out',
            'stop_id' => $location['id'],
            'resolved_location_name' => $location['name'],
            'latitude' => $request->lat,
            'longitude' => $request->lon
        ]);

        // 2. Calculate Fare based on Asset Type
        $fareAmount = 20.00; // Minimum default
        if ($ride->reference_type === Bus::class) {
            $fareAmount = $this->calculateBusFare($ride->tapIn, $tapOut, $ride->reference_id);
        } else {
            $fareAmount = $this->calculateParkingFare($ride->tapIn, $tapOut, $ride->reference_id);
        }

        // 3. Financial Reconciliation (Transaction & Merchant Income)
        if ($fareAmount > 0) {
            $balanceOut = BalanceOut::create([
                'user_id' => $user->id,
                'merchant_id' => $ride->merchant_id,
                'amount' => $fareAmount,
                'type' => $ride->reference_type === Bus::class ? 'fare_deduction' : 'parking',
                'remarks' => "Journey #{$ride->id} completed. From {$ride->tapIn->resolved_location_name} to {$location['name']}",
                'reference_id' => $ride->reference_id,
                'reference_type' => $ride->reference_type,
                'created_by' => 1 // System
            ]);

            MerchantIncome::create([
                'merchant_id' => $ride->merchant_id,
                'balance_out_id' => $balanceOut->id,
                'reference_id' => $ride->reference_id,
                'reference_type' => $ride->reference_type,
                'amount' => $fareAmount,
                'type' => $ride->reference_type === Bus::class ? 'fare' : 'parking',
            ]);
        }

        // 4. Update Reconciled Ride
        $ride->update([
            'tap_out_id' => $tapOut->id,
            'fare_amount' => $fareAmount,
            'status' => 'completed'
        ]);

        logActivity('ride_completed', "Ride finished. Paid Rs. {$fareAmount}", [
            'ride_id' => $ride->id,
            'fare' => $fareAmount,
            'start' => $ride->tapIn->resolved_location_name,
            'end' => $location['name']
        ], $user->id);

        return apiResponse(true, "Tap Out successful at {$location['name']}. Fare: Rs. {$fareAmount}", [
            'type' => 'out',
            'fare' => $fareAmount,
            'new_balance' => $user->balance()
        ]);
    }

    private function resolveAsset($hwId)
    {
        // Try to find as a Bus first, then as a Parking lot ID
        return Bus::where('hwid', $hwId)->first() ?? Parking::where('id', $hwId)->first();
    }

    private function resolveLocation($lat, $lon, $asset)
    {
        $haversine = "(6371 * acos(cos(radians($lat)) * cos(radians(latitude)) * cos(radians(longitude) - radians($lon)) + sin(radians($lat)) * sin(radians(latitude))))";

        if ($asset instanceof Bus) {
            // Find nearest stop on the bus route
            $stop = RouteStop::where('route_id', $asset->route_id)
                ->select('*')
                ->selectRaw("$haversine AS distance")
                ->orderBy('distance')
                ->first();
            
            if (!$stop) return ['id' => null, 'name' => "GPS: $lat, $lon"];

            // Show name of the nearest stop
            $name = $stop->stop_name;
            // If more than 500m away, prefix with "Near"
            if ($stop->distance > 0.5) {
                $name = "Near " . $name;
            }
            
            return ['id' => $stop->id, 'name' => $name];
        } else {
            // Parking lot is fixed location
            return ['id' => $asset->id, 'name' => $asset->name];
        }
    }

    private function calculateBusFare($tapIn, $tapOut, $busId)
    {
        // If same stop or location not resolved, return minimum fare
        if (!$tapIn->stop_id || !$tapOut->stop_id || $tapIn->stop_id == $tapOut->stop_id) return 15.00;
        
        $bus = Bus::find($busId);
        $matrix = FareMatrix::where('fare_id', $bus->active_fare_id)
            ->where('from_stop_id', $tapIn->stop_id)
            ->where('to_stop_id', $tapOut->stop_id)
            ->first();
            
        return $matrix ? $matrix->amount : 20.00;
    }

    private function calculateParkingFare($tapIn, $tapOut, $parkingId)
    {
        $parking = Parking::find($parkingId);
        // Minimum 1 hour
        $durationHours = max(1, ceil($tapIn->created_at->diffInMinutes($tapOut->created_at) / 60));
        
        if ($durationHours <= 1) return $parking->first_hour_fee;
        return $parking->first_hour_fee + (($durationHours - 1) * $parking->onwards_hour_fee);
    }
}
