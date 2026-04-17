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
        $users = User::all();
        $merchants = User::whereHas('roles', fn($q) => $q->where('slug', 'merchant'))->get();
        $admin = User::whereHas('roles', fn($q) => $q->where('slug', 'super-admin'))->first() ?? $users->first();
        $buses = Bus::all();
        $parkings = Parking::all();

        if ($merchants->isEmpty() || $buses->isEmpty() || $parkings->isEmpty()) {
            $this->command->warn("Please ensure Merchants, Buses, and Parkings are seeded before running BalanceSeeder.");
            return;
        }

        foreach ($users as $user) {
            // 1. Initial Credit
            BalanceIn::create([
                'user_id' => $user->id,
                'amount' => rand(5000, 10000),
                'type' => 'manual',
                'remarks' => 'Initial system load',
                'created_by' => $admin->id,
                'status' => 'completed',
                'created_at' => Carbon::now()->subMonths(4),
            ]);

            // 2. Generate 50 mixed transactions
            for ($i = 0; $i < 49; $i++) {
                $isCredit = rand(0, 1);
                $date = Carbon::now()->subDays(rand(1, 120));

                if ($isCredit) {
                    $types = ['manual', 'cashback', 'khalti'];
                    BalanceIn::create([
                        'user_id' => $user->id,
                        'amount' => rand(100, 1000),
                        'type' => $types[array_rand($types)],
                        'remarks' => 'Funds loaded #' . ($i + 1),
                        'created_by' => $admin->id,
                        'status' => 'completed',
                        'created_at' => $date,
                    ]);
                } else {
                    $currentBalance = $user->balance();
                    $deductAmount = rand(20, 200);

                    if ($currentBalance >= $deductAmount) {
                        $activityType = ['fare_deduction', 'parking', 'penalty', 'manual_deduction'][rand(0, 3)];
                        
                        $merchantId = null;
                        $refId = null;
                        $refType = null;

                        if ($activityType === 'fare_deduction') {
                            $bus = $buses->random();
                            $merchantId = $bus->merchant_id;
                            $refId = $bus->id;
                            $refType = 'App\Models\Bus';
                        } elseif ($activityType === 'parking') {
                            $parking = $parkings->random();
                            $merchantId = $parking->merchant_id;
                            $refId = $parking->id;
                            $refType = 'App\Models\Parking';
                        }

                        DB::transaction(function() use ($user, $merchantId, $deductAmount, $activityType, $refId, $refType, $admin, $date) {
                            $out = BalanceOut::create([
                                'user_id' => $user->id,
                                'merchant_id' => $merchantId,
                                'amount' => $deductAmount,
                                'type' => $activityType,
                                'remarks' => 'System generated ' . str_replace('_', ' ', $activityType),
                                'reference_id' => $refId,
                                'reference_type' => $refType,
                                'created_by' => $admin->id,
                                'created_at' => $date,
                            ]);

                            if ($merchantId && in_array($activityType, ['fare_deduction', 'parking'])) {
                                MerchantIncome::create([
                                    'merchant_id' => $merchantId,
                                    'balance_out_id' => $out->id,
                                    'reference_id' => $refId,
                                    'reference_type' => $refType,
                                    'amount' => $deductAmount,
                                    'type' => $activityType === 'fare_deduction' ? 'fare' : 'parking',
                                    'created_at' => $date,
                                ]);
                            }
                        });
                    }
                }
            }
        }

        // 3. Generate some random withdrawals for merchants
        foreach ($merchants as $merchant) {
            $balance = $merchant->merchantBalance();
            if ($balance > 500) {
                for ($j = 0; $j < 3; $j++) {
                    $withdrawAmount = rand(100, floor($balance / 3));
                    MerchantWithdrawal::create([
                        'merchant_id' => $merchant->id,
                        'amount' => $withdrawAmount,
                        'status' => 'completed',
                        'transaction_id' => 'KHLT_SEED_' . str_shuffle(time()),
                        'gateway_name' => 'khalti',
                        'remarks' => 'Seed withdrawal',
                        'created_at' => Carbon::now()->subDays(rand(1, 30)),
                    ]);
                }
            }
        }
    }
}
