<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\Bus;
use App\Models\Parking;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $merchants = User::whereHas('roles', fn($q) => $q->where('slug', 'merchant'))->get();
        $staffRole = Role::where('slug', 'staff')->first();
        $customerRole = Role::where('slug', 'customers')->first();

        if ($merchants->isEmpty() || !$staffRole || !$customerRole) return;

        foreach ($merchants as $index => $merchant) {
            $this->command->info("Creating staff for merchant: {$merchant->name}");
            
            // Create 2 staff members per merchant
            for ($i = 1; $i <= 2; $i++) {
                $email = "staff{$i}.merchant{$merchant->id}@example.com";
                $staff = User::updateOrCreate(
                    ['email' => $email],
                    [
                        'name' => "Staff {$i} for {$merchant->name}",
                        'password' => Hash::make('password'),
                        'status' => 'active',
                    ]
                );

                $staff->roles()->syncWithoutDetaching([$staffRole->id, $customerRole->id]);
                $merchant->staff()->syncWithoutDetaching([$staff->id]);

                // Assign to random buses and parkings of this merchant
                $buses = $merchant->buses->pluck('id');
                if ($buses->isNotEmpty()) {
                    $staff->assignedBuses()->attach($buses->random(min(1, $buses->count())));
                }

                $parkings = $merchant->parkings->pluck('id');
                if ($parkings->isNotEmpty()) {
                    $staff->assignedParkings()->attach($parkings->random(min(1, $parkings->count())));
                }
            }
        }
    }
}
