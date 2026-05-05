<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Bus;
use App\Models\Card;
use App\Models\Ride;
use App\Models\Tap;
use App\Models\BalanceIn;
use App\Models\Fare;
use App\Models\FareMatrix;
use App\Models\Stop;
use App\Models\Route;
use App\Models\RouteStop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RideApiTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $card;
    protected $bus;
    protected $route;
    protected $stopA;
    protected $stopB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        
        // Add balance to user
        BalanceIn::create([
            'user_id' => $this->user->id,
            'amount' => 100,
            'type' => 'manual',
            'status' => 'completed',
            'gateway_name' => 'Test',
            'transaction_id' => 'TXN-123',
        ]);

        $this->card = Card::create([
            'user_id' => $this->user->id,
            'card_number' => '1122334455',
            'hwid' => 'CARD-HWID-01',
            'status' => 'active',
        ]);

        $this->route = Route::create(['name' => 'Route 1', 'direction' => 'inbound']);
        
        $this->stopA = Stop::create(['name' => 'Stop A', 'latitude' => 27.7, 'longitude' => 85.3]);
        $this->stopB = Stop::create(['name' => 'Stop B', 'latitude' => 27.71, 'longitude' => 85.31]);

        RouteStop::create(['route_id' => $this->route->id, 'stop_id' => $this->stopA->id, 'order' => 1, 'stop_name' => 'Stop A', 'latitude' => 27.7, 'longitude' => 85.3]);
        RouteStop::create(['route_id' => $this->route->id, 'stop_id' => $this->stopB->id, 'order' => 2, 'stop_name' => 'Stop B', 'latitude' => 27.71, 'longitude' => 85.31]);

        $this->bus = Bus::create([
            'name' => 'Bus 1',
            'bus_number' => 'BA 1 PA 1111',
            'hwid' => 'BUS-HWID-01',
            'status' => 'active',
            'route_id' => $this->route->id,
            'merchant_id' => 1, // Dummy merchant id
        ]);

        $fare = Fare::create([
            'name' => 'Fare 1',
            'route_id' => $this->route->id,
            'status' => 'approved',
            'effective_from' => now(),
        ]);

        FareMatrix::create([
            'fare_id' => $fare->id,
            'from_stop_id' => $this->stopA->id,
            'to_stop_id' => $this->stopB->id,
            'amount' => 25,
        ]);

        $this->bus->update(['active_fare_id' => $fare->id]);
    }

    public function test_user_can_tap_in()
    {
        Sanctum::actingAs($this->user, ['access']);

        $response = $this->postJson('/api/tap', [
            'lat' => 27.7,
            'lon' => 85.3,
            'card_number' => '1122334455',
            'hw_id' => 'BUS-HWID-01',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'content' => [
                    'type' => 'in',
                    'location' => 'Stop A',
                ]
            ]);

        $this->assertDatabaseHas('rides', [
            'user_id' => $this->user->id,
            'card_id' => $this->card->id,
            'status' => 'ongoing',
        ]);

        $this->assertDatabaseHas('taps', [
            'type' => 'in',
            'stop_id' => $this->stopA->id,
        ]);
    }

    public function test_user_can_tap_out_and_complete_ride()
    {
        Sanctum::actingAs($this->user, ['access']);

        // First Tap In
        $this->postJson('/api/tap', [
            'lat' => 27.7,
            'lon' => 85.3,
            'card_number' => '1122334455',
            'hw_id' => 'BUS-HWID-01',
        ]);

        // Second Tap Out
        $response = $this->postJson('/api/tap', [
            'lat' => 27.71,
            'lon' => 85.31,
            'card_number' => '1122334455',
            'hw_id' => 'BUS-HWID-01',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'content' => [
                    'type' => 'out',
                    'fare_pts' => 25,
                ]
            ]);

        $this->assertDatabaseHas('rides', [
            'user_id' => $this->user->id,
            'card_id' => $this->card->id,
            'status' => 'completed',
            'fare_amount' => 25,
        ]);

        $this->assertEquals(75, $this->user->fresh()->balance());
    }

    public function test_tap_in_fails_with_insufficient_balance()
    {
        $poorUser = User::factory()->create();
        $poorCard = Card::create([
            'user_id' => $poorUser->id,
            'card_number' => '9988776655',
            'hwid' => 'POOR-HWID',
            'status' => 'active',
        ]);

        Sanctum::actingAs($poorUser, ['access']);

        $response = $this->postJson('/api/tap', [
            'lat' => 27.7,
            'lon' => 85.3,
            'card_number' => '9988776655',
            'hw_id' => 'BUS-HWID-01',
        ]);

        $response->assertStatus(402)
            ->assertJson([
                'status' => false,
                'message' => 'Insufficient balance (Min 20 pts required to start a journey)',
            ]);
    }
}
