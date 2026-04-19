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
     * Handle Tap In / Tap Out via API.
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

        $card = Card::where('card_number', $request->card_number)->firstOrFail();
        
        // Detect asset (Bus or Parking)
        $asset = Bus::where('hwid', $request->hw_id)->first();
        if (!$asset) {
            // Check if it's a parking HWID (assuming parking has HWID or similar)
            // For now let's assume hw_id is enough to find either
            $asset = Parking::where('id', $request->hw_id)->first(); // Fallback to ID for demo
        }

        if (!$asset) {
            return apiResponse(false, 'Scanner (Asset) not found', '', 404);
        }

        $user = $card->user;
        if (!$user) return apiResponse(false, 'Card not assigned', '', 400);
        if ($card->status !== 'active') return apiResponse(false, 'Card inactive', '', 403);

        // Check for an ongoing ride for this card
        $ongoingRide = Ride::where('card_id', $card->id)
            ->where('status', 'ongoing')
            ->first();

        return DB::transaction(function() use ($request, $user, $card, $asset, $ongoingRide) {
            if ($ongoingRide) {
                return $this->handleTapOut($request, $ongoingRide, $asset);
            } else {
                return $this->handleTapIn($request, $user, $card, $asset);
            }
        });
    }

    private function handleTapIn($request, $user, $card, $asset)
    {
        if ($user->balance() < 20) {
            return apiResponse(false, 'Insufficient balance (Min Rs. 20)', '', 402);
        }

        // Normalize Location (Geofencing)
        $location = $this->resolveLocation($request->lat, $request->lon, $asset);

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

        $ride = Ride::create([
            'user_id' => $user->id,
            'card_id' => $card->id,
            'merchant_id' => $asset->merchant_id,
            'reference_id' => $asset->id,
            'reference_type' => get_class($asset),
            'tap_in_id' => $tap->id,
            'status' => 'ongoing'
        ]);

        logActivity('tap_in', "Tapped in at {$location['name']}", [
            'ride_id' => $ride->id,
            'asset' => $asset->name
        ], $user->id);

        return apiResponse(true, "Tap In at {$location['name']}", ['type' => 'in']);
    }

    private function handleTapOut($request, $ride, $currentAsset)
    {
        $user = $ride->user;
        $location = $this->resolveLocation($request->lat, $request->lon, $currentAsset);

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

        // Fare Logic
        $fareAmount = 20.00; // Default
        if ($ride->reference_type === Bus::class) {
            $fareAmount = $this->calculateBusFare($ride->tapIn, $tapOut, $ride->reference_id);
        } else {
            $fareAmount = $this->calculateParkingFare($ride->tapIn, $tapOut, $ride->reference_id);
        }

        // Transaction Reconcillation
        if ($fareAmount > 0) {
            $balanceOut = BalanceOut::create([
                'user_id' => $user->id,
                'merchant_id' => $ride->merchant_id,
                'amount' => $fareAmount,
                'type' => $ride->reference_type === Bus::class ? 'fare_deduction' : 'parking',
                'remarks' => "Ride #{$ride->id} completed at {$location['name']}",
                'reference_id' => $ride->reference_id,
                'reference_type' => $ride->reference_type,
                'created_by' => 1
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

        $ride->update([
            'tap_out_id' => $tapOut->id,
            'fare_amount' => $fareAmount,
            'status' => 'completed'
        ]);

        logActivity('ride_completed', "Journey finished. Paid Rs. {$fareAmount}", [
            'ride_id' => $ride->id,
            'from' => $ride->tapIn->resolved_location_name,
            'to' => $location['name']
        ], $user->id);

        return apiResponse(true, "Tap Out at {$location['name']}. Fare: Rs. {$fareAmount}", ['type' => 'out', 'fare' => $fareAmount]);
    }

    private function resolveLocation($lat, $lon, $asset)
    {
        // Geo-fencing: find nearest stop within 150m (0.15km)
        $threshold = 0.15; 
        $haversine = "(6371 * acos(cos(radians($lat)) * cos(radians(latitude)) * cos(radians(longitude) - radians($lon)) + sin(radians($lat)) * sin(radians(latitude))))";

        if ($asset instanceof Bus) {
            $stop = RouteStop::where('route_id', $asset->route_id)
                ->select('*')
                ->selectRaw("$haversine AS distance")
                ->having("distance", "<=", $threshold)
                ->orderBy('distance')
                ->first();
            
            return $stop ? ['id' => $stop->id, 'name' => $stop->stop_name] : ['id' => null, 'name' => 'Moving (GPS)'];
        } else {
            // For Parking, the asset itself is the location
            return ['id' => $asset->id, 'name' => $asset->name];
        }
    }

    private function calculateBusFare($tapIn, $tapOut, $busId)
    {
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
        $durationHours = ceil($tapIn->created_at->diffInMinutes($tapOut->created_at) / 60);
        
        if ($durationHours <= 1) return $parking->first_hour_fee;
        return $parking->first_hour_fee + (($durationHours - 1) * $parking->onwards_hour_fee);
    }
}
