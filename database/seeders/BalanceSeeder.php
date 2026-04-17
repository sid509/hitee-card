<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\BalanceIn;
use App\Models\BalanceOut;
use App\Models\MerchantIncome;
use App\Models\MerchantWithdrawal;
use App\Models\Bus;
use App\Models\Parking;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BalanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::whereHas('roles', fn($q) => $q->where('slug', 'customers'))->get();
        $merchants = User::whereHas('roles', fn($q) => $q->where('slug', 'merchant'))->get();
        $admin = User::whereHas('roles', fn($q) => $q->where('slug', 'super-admin'))->first();
        
        $buses = Bus::with(['activeFare.matrices'])->get();
        $parkings = Parking::all();

        if ($users->isEmpty() || $buses->isEmpty()) return;

        foreach ($users as $user) {
            // 1. Initial Heavy Fund Loading (to ensure they don't run out)
            BalanceIn::create([
                'user_id' => $user->id,
                'amount' => 15000,
                'type' => 'manual',
                'remarks' => 'Welcome system load',
                'created_by' => $admin->id,
                'status' => 'completed',
                'created_at' => Carbon::now()->subMonths(6),
            ]);

            // 2. Generate 100 realistic transactions per user
            for ($i = 0; $i < 100; $i++) {
                $date = Carbon::now()->subDays(rand(1, 180)); // Last 6 months
                $isCredit = rand(0, 10) > 8; // 20% chance of topup, 80% chance of spending

                if ($isCredit) {
                    $types = ['manual', 'cashback', 'khalti'];
                    $type = $types[array_rand($types)];
                    BalanceIn::create([
                        'user_id' => $user->id,
                        'amount' => rand(500, 2000),
                        'type' => $type,
                        'remarks' => $type == 'khalti' ? 'Self topup' : 'System adjustment',
                        'created_by' => $admin->id,
                        'status' => 'completed',
                        'created_at' => $date,
                    ]);
                } else {
                    // Spending: 70% Bus, 30% Parking
                    $isBus = rand(0, 10) > 3;

                    if ($isBus) {
                        $bus = $buses->random();
                        $amount = 25; // Default

                        // Use actual fare from matrix if exists
                        if ($bus->activeFare && $bus->activeFare->matrices->isNotEmpty()) {
                            $amount = $bus->activeFare->matrices->random()->amount;
                        }

                        DB::transaction(function() use ($user, $bus, $amount, $date, $admin) {
                            $out = BalanceOut::create([
                                'user_id' => $user->id,
                                'merchant_id' => $bus->merchant_id,
                                'amount' => $amount,
                                'type' => 'fare_deduction',
                                'remarks' => 'Travel fare on ' . $bus->bus_number,
                                'reference_id' => $bus->id,
                                'reference_type' => 'App\Models\Bus',
                                'created_by' => $admin->id,
                                'created_at' => $date,
                            ]);

                            MerchantIncome::create([
                                'merchant_id' => $bus->merchant_id,
                                'balance_out_id' => $out->id,
                                'reference_id' => $bus->id,
                                'reference_type' => 'App\Models\Bus',
                                'amount' => $amount,
                                'type' => 'fare',
                                'created_at' => $date,
                            ]);
                        });
                    } else {
                        $parking = $parkings->random();
                        $amount = rand(20, 100);

                        DB::transaction(function() use ($user, $parking, $amount, $date, $admin) {
                            $out = BalanceOut::create([
                                'user_id' => $user->id,
                                'merchant_id' => $parking->merchant_id,
                                'amount' => $amount,
                                'type' => 'parking',
                                'remarks' => 'Parking fee at ' . $parking->name,
                                'reference_id' => $parking->id,
                                'reference_type' => 'App\Models\Parking',
                                'created_by' => $admin->id,
                                'created_at' => $date,
                            ]);

                            MerchantIncome::create([
                                'merchant_id' => $parking->merchant_id,
                                'balance_out_id' => $out->id,
                                'reference_id' => $parking->id,
                                'reference_type' => 'App\Models\Parking',
                                'amount' => $amount,
                                'type' => 'parking',
                                'created_at' => $date,
                            ]);
                        });
                    }
                }
            }
        }

        // 3. Realistic Merchant Withdrawals
        foreach ($merchants as $merchant) {
            $totalIncome = $merchant->merchantIncomes()->sum('amount');
            if ($totalIncome > 2000) {
                // Withdraw 70% of income in 5-10 batches
                $withdrawTarget = $totalIncome * 0.7;
                $batches = rand(5, 10);
                $batchAmount = $withdrawTarget / $batches;

                for ($j = 0; $j < $batches; $j++) {
                    MerchantWithdrawal::create([
                        'merchant_id' => $merchant->id,
                        'amount' => $batchAmount,
                        'status' => 'completed',
                        'transaction_id' => 'KHLT_WD_' . bin2hex(random_bytes(4)),
                        'gateway_name' => 'khalti',
                        'remarks' => 'Settlement to bank',
                        'created_at' => Carbon::now()->subDays(rand(1, 150)),
                    ]);
                }
            }
        }
    }
}
