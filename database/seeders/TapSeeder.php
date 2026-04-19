<?php

namespace Database\Seeders;

use App\Models\Bus;
use App\Models\Card;
use App\Models\Parking;
use App\Models\Ride;
use App\Models\Tap;
use App\Models\RouteStop;
use App\Models\BalanceOut;
use App\Models\MerchantIncome;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TapSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cards = Card::where('status', 'active')->where('is_currently_active', true)->with('user')->get();
        $buses = Bus::all();
        $parkings = Parking::all();

        if ($cards->isEmpty() || $buses->isEmpty()) {
            $this->command->warn('Ensure you have cards and buses seeded before running TapSeeder.');
            return;
        }

        foreach ($cards->take(10) as $card) {
            $user = $card->user;
            if (!$user) continue;

            // Generate 5-10 rides per user
            $rideCount = rand(5, 10);
            
            for ($i = 0; $i < $rideCount; $i++) {
                $isBus = rand(0, 1) == 1;
                $asset = $isBus ? $buses->random() : $parkings->random();
                
                $date = Carbon::now()->subDays(rand(0, 30))->subHours(rand(0, 23));
                
                DB::transaction(function() use ($user, $card, $asset, $date, $isBus) {
                    // 1. Tap In
                    $startStop = null;
                    if ($isBus) {
                        $startStop = RouteStop::where('route_id', $asset->route_id)->inRandomOrder()->first();
                    }

                    $tapIn = Tap::create([
                        'user_id' => $user->id,
                        'card_id' => $card->id,
                        'merchant_id' => $asset->merchant_id,
                        'reference_id' => $asset->id,
                        'reference_type' => get_class($asset),
                        'type' => 'in',
                        'stop_id' => $startStop?->id,
                        'resolved_location_name' => $startStop?->stop_name ?? $asset->name,
                        'latitude' => $startStop?->latitude ?? $asset->latitude,
                        'longitude' => $startStop?->longitude ?? $asset->longitude,
                        'created_at' => $date,
                        'updated_at' => $date,
                    ]);

                    // 2. Tap Out (randomly leave some ongoing)
                    $isOngoing = rand(1, 10) > 8; // 20% chance to be ongoing

                    if ($isOngoing) {
                         Ride::create([
                            'user_id' => $user->id,
                            'card_id' => $card->id,
                            'merchant_id' => $asset->merchant_id,
                            'reference_id' => $asset->id,
                            'reference_type' => get_class($asset),
                            'tap_in_id' => $tapIn->id,
                            'status' => 'ongoing',
                            'created_at' => $date,
                        ]);
                    } else {
                        $outDate = (clone $date)->addMinutes(rand(15, 120));
                        $endStop = null;
                        if ($isBus && $startStop) {
                            $endStop = RouteStop::where('route_id', $asset->route_id)
                                ->where('order', '>', $startStop->order)
                                ->orderBy('order')
                                ->first() ?? $startStop;
                        }

                        $tapOut = Tap::create([
                            'user_id' => $user->id,
                            'card_id' => $card->id,
                            'merchant_id' => $asset->merchant_id,
                            'reference_id' => $asset->id,
                            'reference_type' => get_class($asset),
                            'type' => 'out',
                            'stop_id' => $endStop?->id,
                            'resolved_location_name' => $endStop?->stop_name ?? $asset->name,
                            'latitude' => $endStop?->latitude ?? $asset->latitude,
                            'longitude' => $endStop?->longitude ?? $asset->longitude,
                            'created_at' => $outDate,
                            'updated_at' => $outDate,
                        ]);

                        $fare = $isBus ? rand(15, 45) : rand(20, 100);
                        $ride = Ride::create([
                            'user_id' => $user->id,
                            'card_id' => $card->id,
                            'merchant_id' => $asset->merchant_id,
                            'reference_id' => $asset->id,
                            'reference_type' => get_class($asset),
                            'tap_in_id' => $tapIn->id,
                            'tap_out_id' => $tapOut->id,
                            'fare_amount' => $fare,
                            'status' => 'completed',
                            'created_at' => $date,
                            'updated_at' => $outDate,
                        ]);

                        // Transaction record
                        $balanceOut = BalanceOut::create([
                            'user_id' => $user->id,
                            'merchant_id' => $asset->merchant_id,
                            'amount' => $fare,
                            'type' => $isBus ? 'fare_deduction' : 'parking',
                            'remarks' => "Ride #{$ride->id} completed",
                            'reference_id' => $asset->id,
                            'reference_type' => get_class($asset),
                            'created_by' => 1,
                            'created_at' => $outDate,
                        ]);

                        MerchantIncome::create([
                            'merchant_id' => $asset->merchant_id,
                            'balance_out_id' => $balanceOut->id,
                            'reference_id' => $asset->id,
                            'reference_type' => get_class($asset),
                            'amount' => $fare,
                            'type' => $isBus ? 'fare' : 'parking',
                            'created_at' => $outDate,
                        ]);
                    }
                });
            }
        }
    }
}
