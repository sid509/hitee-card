<?php

namespace Database\Seeders;

use App\Models\CardApplication;
use App\Models\User;
use Illuminate\Database\Seeder;

class CardApplicationSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::whereHas('roles', function($q) {
            $q->where('slug', 'customers');
        })->inRandomOrder()->limit(3)->get();

        foreach ($users as $index => $user) {
            $status = ['pending', 'approved', 'rejected'][$index % 3];
            CardApplication::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'type' => 'personalized',
                    'status' => $status,
                    'kyc_data' => [
                        'full_name' => $user->name,
                        'delivery_address' => 'Kathmandu, Ward ' . rand(1, 32),
                        'phone_number' => $user->phone_number,
                    ],
                    'admin_remarks' => $status == 'rejected' ? 'Invalid address' : null,
                ]
            );
        }
    }
}
