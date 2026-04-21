<?php

namespace Database\Seeders;

use App\Models\Card;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = User::whereHas('roles', function($q){
            $q->where('slug', 'customers');
        })->get();

        if ($customers->isEmpty()) return;

        foreach ($customers as $user) {
            $card = Card::create([
                'card_number' => "CRD-" . strtoupper(Str::random(10)),
                'hwid' => "HW-" . strtoupper(Str::random(12)),
                'status' => 'active',
                'is_currently_active' => true,
                'user_id' => $user->id,
            ]);

            // Initial Balance for User-linked Card
            \App\Models\BalanceIn::create([
                'user_id' => $user->id,
                'card_id' => $card->id,
                'amount' => 500,
                'type' => 'manual',
                'remarks' => 'Initial card balance',
                'status' => 'completed',
            ]);
        }

        // Add some extra inactive/blocked cards for realism
        for ($i = 1; $i <= 10; $i++) {
            $user = $customers->random();
            $card = Card::create([
                'card_number' => "CRD-" . strtoupper(Str::random(10)),
                'hwid' => "HW-" . strtoupper(Str::random(12)),
                'status' => ['inactive', 'blocked'][rand(0, 1)],
                'is_currently_active' => false,
                'user_id' => $user->id,
            ]);

            // Initial Balance even for inactive cards (they might have been used before)
            \App\Models\BalanceIn::create([
                'user_id' => $user->id,
                'card_id' => $card->id,
                'amount' => 500,
                'type' => 'manual',
                'remarks' => 'Initial card balance',
                'status' => 'completed',
            ]);
        }

        // Add some orphan (unlinked) cards
        for ($i = 1; $i <= 5; $i++) {
            $card = Card::create([
                'card_number' => "INST-" . strtoupper(Str::random(8)),
                'hwid' => "HW-" . strtoupper(Str::random(12)),
                'status' => 'active',
                'is_currently_active' => false, // Will be set true when used or linked
                'user_id' => null,
            ]);

            // Initial Balance for Orphan/Instant Card
            \App\Models\BalanceIn::create([
                'user_id' => null,
                'card_id' => $card->id,
                'amount' => 500,
                'type' => 'manual',
                'remarks' => 'Initial instant card balance',
                'status' => 'completed',
            ]);
        }
    }
}
