<?php

namespace Tests\Feature\Api\Merchant;

use App\Models\Bus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MerchantBusApiTest extends TestCase
{
    use RefreshDatabase;

    protected $merchant;
    protected $role;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = Role::create(['name' => 'Merchant', 'slug' => 'merchant']);
        $this->merchant = User::create([
            'name' => 'Test Merchant',
            'email' => 'merchant@example.com',
            'phone_number' => '9841234567',
            'password' => Hash::make('password'),
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
        $this->merchant->roles()->attach($this->role);
    }

    public function test_merchant_can_list_buses()
    {
        Bus::create([
            'name' => 'Test Bus 1',
            'bus_number' => 'BA 1 PA 1111',
            'hwid' => 'HWID111',
            'merchant_id' => $this->merchant->id,
            'status' => 'active'
        ]);

        $otherMerchant = User::create([
            'name' => 'Other Merchant',
            'email' => 'other@merchant.com',
            'phone_number' => '9800000000',
            'password' => Hash::make('password'),
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        Bus::create([
            'name' => 'Other Bus',
            'bus_number' => 'BA 1 PA 2222',
            'hwid' => 'HWID222',
            'merchant_id' => $otherMerchant->id,
            'status' => 'active'
        ]);

        Sanctum::actingAs($this->merchant);
        $response = $this->getJson('/api/merchant/buses');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'content')
            ->assertJsonFragment(['bus_number' => 'BA 1 PA 1111'])
            ->assertJsonMissing(['bus_number' => 'BA 1 PA 2222']);
    }

    public function test_merchant_can_show_bus()
    {
        $bus = Bus::create([
            'name' => 'Test Bus 1',
            'bus_number' => 'BA 1 PA 1111',
            'hwid' => 'HWID111',
            'merchant_id' => $this->merchant->id,
            'status' => 'active'
        ]);

        Sanctum::actingAs($this->merchant);
        $response = $this->getJson("/api/merchant/buses/{$bus->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['bus_number' => 'BA 1 PA 1111'])
            ->assertJsonStructure([
                'status',
                'content' => [
                    'id', 'name', 'bus_number', 'active_fare', 'route'
                ]
            ]);
    }

    public function test_merchant_cannot_show_other_merchant_bus()
    {
        $otherMerchant = User::create([
            'name' => 'Other Merchant',
            'email' => 'other@merchant.com',
            'phone_number' => '9800000000',
            'password' => Hash::make('password'),
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $bus = Bus::create([
            'name' => 'Other Bus',
            'bus_number' => 'BA 1 PA 2222',
            'hwid' => 'HWID222',
            'merchant_id' => $otherMerchant->id,
            'status' => 'active'
        ]);

        Sanctum::actingAs($this->merchant);
        $response = $this->getJson("/api/merchant/buses/{$bus->id}");

        $response->assertStatus(403);
    }
}
