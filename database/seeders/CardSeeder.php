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

        for ($i = 1; $i <= 32; $i++) {
            $user = $customers->random();
            
            // Check if user already has an active card
            $hasActive = Card::where('user_id', $user->id)->where('is_currently_active', true)->exists();
            $isActive = !$hasActive && rand(0, 1);

            Card::create([
                'card_number' => "CRD-" . strtoupper(Str::random(10)),
                'hwid' => "HW-" . strtoupper(Str::random(12)),
                'status' => ['active', 'inactive', 'blocked'][rand(0, 2)],
                'is_currently_active' => $isActive,
                'user_id' => $user->id,
            ]);
        }
    }
}
