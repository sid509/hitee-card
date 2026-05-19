<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Ride;
use App\Models\Tap;
use App\Models\SubscriptionDiscount;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CardPageController extends Controller
{
    /**
     * Get statistics and info for the user's active card.
     */
    public function index()
    {
        $user = auth()->user();
        $card = $user->activeCard;

        if (!$card) {
            return response()->json([
                'success' => false,
                'message' => 'No active card found for user.'
            ], 404);
        }

        $card->load(['subscriptionModels.discounts.servicePartner']);

        // 1. Travel Stats
        $rides = Ride::where('card_id', $card->id)->where('status', 'completed')->get();
        $travelCount = $rides->count();
        $totalDistance = 0; // Placeholder: Distance calculation would need route stop data
        
        // 2. Parking Stats
        $parkingTaps = Tap::where('card_id', $card->id)
            ->where('reference_type', 'App\Models\Parking')
            ->orderBy('created_at', 'asc')
            ->get();

        $totalParkingMinutes = 0;
        $tempInTap = null;

        foreach ($parkingTaps as $tap) {
            if ($tap->type === 'in') {
                $tempInTap = $tap;
            } elseif ($tap->type === 'out' && $tempInTap) {
                $totalParkingMinutes += $tap->created_at->diffInMinutes($tempInTap->created_at);
                $tempInTap = null;
            }
        }

        // 3. Discounts
        $discounts = [];
        foreach ($card->subscriptionModels as $model) {
            foreach ($model->discounts as $d) {
                $discounts[] = [
                    'partner_name' => $d->servicePartner->name,
                    'service_type' => $d->servicePartner->service_type,
                    'discount' => ($d->discount_type == 'percentage' ? $d->discount_value . '%' : 'Rs. ' . $d->discount_value),
                    'description' => $d->description
                ];
            }
        }

        return response()->json([
            'success' => true,
            'card' => [
                'card_number' => $card->card_number,
                'type' => $card->subscriptionModels->pluck('name')->implode(', ') ?: 'Standard',
                'is_personalized' => $card->is_personalized,
                'is_physical' => $card->is_physical,
                'balance' => $card->balance(),
            ],
            'stats' => [
                'travel_rides' => $travelCount,
                'travel_distance_km' => $totalDistance,
                'parking_time_minutes' => $totalParkingMinutes,
                'parking_time_formatted' => $this->formatMinutes($totalParkingMinutes),
                'discount_availed' => 0, // Placeholder
            ],
            'available_discounts' => $discounts
        ]);
    }

    private function formatMinutes($minutes)
    {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        if ($hours > 0) {
            return "{$hours}h {$mins}m";
        }
        return "{$mins}m";
    }
}
