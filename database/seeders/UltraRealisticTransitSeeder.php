<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Bus;
use App\Models\Card;
use App\Models\Ride;
use App\Models\Tap;
use App\Models\Fare;
use App\Models\FareMatrix;
use App\Models\BalanceIn;
use App\Models\BalanceOut;
use App\Models\MerchantIncome;
use App\Models\MerchantWithdrawal;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UltraRealisticTransitSeeder extends Seeder
{
    private function simulateRidesForCard($card, $user, $buses, $rideCount)
    {
        for ($i = 0; $i < $rideCount; $i++) {
            $bus = $buses->random();
            $stops = $bus->route->stops;
            if ($stops->count() < 2) continue;

            // Pick two random stops in order
            $startIdx = rand(0, $stops->count() - 2);
            $endIdx = rand($startIdx + 1, $stops->count() - 1);
            $startStop = $stops[$startIdx];
            $endStop = $stops[$endIdx];

            $rideDate = Carbon::now()->subDays(rand(1, 120))->subHours(rand(0, 23))->subMinutes(rand(0, 59));
            $completionDate = (clone $rideDate)->addMinutes(rand(15, 90));

            DB::transaction(function() use ($user, $card, $bus, $startStop, $endStop, $rideDate, $completionDate) {
                // --- STEP 1: TAP IN ---
                $tapIn = Tap::create([
                    'user_id' => $user?->id,
                    'card_id' => $card->id,
                    'merchant_id' => $bus->merchant_id,
                    'reference_id' => $bus->id,
                    'reference_type' => 'App\Models\Bus',
                    'type' => 'in',
                    'stop_id' => $startStop->id,
                    'resolved_location_name' => $startStop->stop_name,
                    'latitude' => $startStop->latitude,
                    'longitude' => $startStop->longitude,
                    'created_at' => $rideDate,
                ]);

                // --- STEP 2: CALCULATE FARE ---
                $fareAmount = 20; // fallback
                $matrix = FareMatrix::where('fare_id', $bus->active_fare_id)
                    ->where('from_stop_id', $startStop->id)
                    ->where('to_stop_id', $endStop->id)
                    ->first();
                if ($matrix) $fareAmount = $matrix->amount;

                // --- STEP 3: TAP OUT ---
                $tapOut = Tap::create([
                    'user_id' => $user?->id,
                    'card_id' => $card->id,
                    'merchant_id' => $bus->merchant_id,
                    'reference_id' => $bus->id,
                    'reference_type' => 'App\Models\Bus',
                    'type' => 'out',
                    'stop_id' => $endStop->id,
                    'resolved_location_name' => $endStop->stop_name,
                    'latitude' => $endStop->latitude,
                    'longitude' => $endStop->longitude,
                    'created_at' => $completionDate,
                ]);

                // --- STEP 4: RIDE RECORD ---
                $ride = Ride::create([
                    'user_id' => $user?->id,
                    'card_id' => $card->id,
                    'merchant_id' => $bus->merchant_id,
                    'reference_id' => $bus->id,
                    'reference_type' => 'App\Models\Bus',
                    'tap_in_id' => $tapIn->id,
                    'tap_out_id' => $tapOut->id,
                    'fare_amount' => $fareAmount,
                    'status' => 'completed',
                    'created_at' => $rideDate,
                    'updated_at' => $completionDate,
                ]);

                // --- STEP 5: FINANCIAL TRANSACTION ---
                // Deduct from card balance
                $balanceOut = BalanceOut::create([
                    'user_id' => $user?->id,
                    'card_id' => $card->id,
                    'merchant_id' => $bus->merchant_id,
                    'amount' => $fareAmount,
                    'type' => 'fare_deduction',
                    'remarks' => "Travel fare on {$bus->bus_number} from {$startStop->stop_name} to {$endStop->stop_name}",
                    'reference_id' => $bus->id,
                    'reference_type' => 'App\Models\Bus',
                    'created_by' => 1, // System
                    'created_at' => $completionDate,
                ]);

                // Credit to merchant(s) associated with the fare
                $merchants = $bus->activeFare->merchants;
                $count = $merchants->count() ?: 1;
                $splitAmount = $fareAmount / $count;

                foreach ($merchants as $m) {
                    MerchantIncome::create([
                        'merchant_id' => $m->id,
                        'balance_out_id' => $balanceOut->id,
                        'reference_id' => $bus->id,
                        'reference_type' => 'App\Models\Bus',
                        'amount' => $splitAmount,
                        'type' => 'fare',
                        'created_at' => $completionDate,
                    ]);
                }
            });
        }
    }

    public function run(): void
    {
        $customers = User::whereHas('roles', fn($q) => $q->where('slug', 'customers'))->with('cards')->get();
        $buses = Bus::whereNotNull('route_id')->whereNotNull('active_fare_id')->with(['route.stops', 'activeFare.matrices', 'activeFare.merchants'])->get();
        $admin = User::whereHas('roles', fn($q) => $q->where('slug', 'super-admin'))->first();

        $this->command->info('Customers count: ' . $customers->count());
        $this->command->info('Buses count: ' . $buses->count());

        if ($customers->isEmpty() || $buses->isEmpty()) {
            $this->command->error('Missing customers or configured buses for simulation.');
            return;
        }

        $this->command->info('Simulating ultra-realistic transit journeys...');

        foreach ($customers as $user) {
            $card = $user->cards->where('is_currently_active', true)->first();
            if (!$card) continue;

            // 1. Initial Balance Load
            BalanceIn::create([
                'user_id' => $user->id,
                'card_id' => $card->id,
                'amount' => 5000,
                'type' => 'manual',
                'remarks' => 'Opening balance',
                'created_by' => $admin->id,
                'status' => 'completed',
                'created_at' => Carbon::now()->subMonths(4),
            ]);

            // 2. Simulate Realistic Rides
            $this->simulateRidesForCard($card, $user, $buses, rand(20, 30));
        }

        // 2.5 Simulate rides for orphan/instant cards
        $orphanCards = Card::whereNull('user_id')->get();
        foreach ($orphanCards as $card) {
            $this->command->info("Simulating rides for orphan card: {$card->card_number}");
            $this->simulateRidesForCard($card, null, $buses, rand(5, 10));
        }

        // 3. Finalize with some Merchant Withdrawals

        // 3. Finalize with some Merchant Withdrawals
        $merchants = User::whereHas('roles', fn($q) => $q->where('slug', 'merchant'))->get();
        foreach ($merchants as $m) {
            $income = MerchantIncome::where('merchant_id', $m->id)->sum('amount');
            if ($income > 1000) {
                MerchantWithdrawal::create([
                    'merchant_id' => $m->id,
                    'amount' => $income * 0.5,
                    'status' => 'completed',
                    'transaction_id' => 'KHLT_SIM_' . bin2hex(random_bytes(4)),
                    'gateway_name' => 'khalti',
                    'remarks' => 'Automatic weekly settlement',
                    'created_at' => Carbon::now()->subDays(rand(1, 10)),
                ]);
            }
        }

        $this->command->info('Simulation completed successfully.');
    }
}
