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
            Card::create([
                'card_number' => "CRD-" . strtoupper(Str::random(10)),
                'hwid' => "HW-" . strtoupper(Str::random(12)),
                'status' => 'active',
                'is_currently_active' => true,
                'user_id' => $user->id,
            ]);
        }

        // Add some extra inactive/blocked cards for realism
        for ($i = 1; $i <= 10; $i++) {
            $user = $customers->random();
            Card::create([
                'card_number' => "CRD-" . strtoupper(Str::random(10)),
                'hwid' => "HW-" . strtoupper(Str::random(12)),
                'status' => ['inactive', 'blocked'][rand(0, 1)],
                'is_currently_active' => false,
                'user_id' => $user->id,
            ]);
        }
    }
}
