<?php

namespace Tests\Feature\Api\Merchant;

use App\Models\User;
use App\Models\Role;
use App\Models\Parking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MerchantParkingApiTest extends TestCase
{
    use RefreshDatabase;

    protected $merchant;
    protected $role;
    protected $parking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = Role::create(['name' => 'Merchant', 'slug' => 'merchant']);
        $this->merchant = User::create([
            'name' => 'Test Merchant',
            'email' => 'merchant@example.com',
            'phone_number' => '9841234567',
            'password' => \Hash::make('password'),
            'status' => User::STATUS_ACTIVE,
        ]);
        $this->merchant->roles()->attach($this->role);

        $this->parking = Parking::create([
            'merchant_id' => $this->merchant->id,
            'name' => 'Merchant Parking',
            'location' => 'Kathmandu',
            'status' => 'opened',
            'latitude' => 27.7,
            'longitude' => 85.3,
            'total_capacity' => 50,
        ]);
    }

    public function test_merchant_can_list_their_parkings()
    {
        Sanctum::actingAs($this->merchant);

        $response = $this->getJson('/api/merchant/parkings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'content' => [
                    '*' => ['id', 'name', 'location', 'total_capacity', 'current_occupancy', 'fees']
                ]
            ]);
        
        $this->assertEquals(1, count($response->json('content')));
    }

    public function test_merchant_can_view_their_parking_details()
    {
        Sanctum::actingAs($this->merchant);

        $response = $this->getJson("/api/merchant/parkings/{$this->parking->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'content' => [
                    'id' => $this->parking->id,
                    'name' => 'Merchant Parking'
                ]
            ]);
    }

    public function test_merchant_cannot_view_others_parking()
    {
        $otherMerchant = User::create([
            'name' => 'Other Merchant',
            'email' => 'other@example.com',
            'phone_number' => '9800000001',
            'password' => \Hash::make('password'),
            'status' => User::STATUS_ACTIVE,
        ]);
        $otherMerchant->roles()->attach($this->role);

        Sanctum::actingAs($otherMerchant);

        $response = $this->getJson("/api/merchant/parkings/{$this->parking->id}");

        $response->assertStatus(403);
    }

    public function test_staff_can_view_their_merchants_parking()
    {
        $staff = User::create([
            'name' => 'Staff Member',
            'email' => 'staff@example.com',
            'phone_number' => '9841000000',
            'password' => \Hash::make('password'),
            'status' => User::STATUS_ACTIVE,
        ]);
        
        // Link staff to merchant
        $this->merchant->staff()->attach($staff);

        Sanctum::actingAs($staff);
        $response = $this->getJson("/api/merchant/parkings/{$this->parking->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'content' => [
                    'id' => $this->parking->id,
                    'name' => 'Merchant Parking'
                ]
            ]);
    }
}
