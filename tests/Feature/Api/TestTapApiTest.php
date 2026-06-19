<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Bus;
use App\Models\Card;
use App\Models\BalanceIn;
use App\Models\Fare;
use App\Models\FareMatrix;
use App\Models\Stop;
use App\Models\Route;
use App\Models\RouteStop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestTapApiTest extends TestCase
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
            'merchant_id' => 1,
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

    public function test_test_tap_route_accepts_get_and_post()
    {
        // 1. Send GET request to /api/test-tap (Tap In)
        $response = $this->get('/api/test-tap');
        $response->assertStatus(200);
        $this->assertStringContainsString('SUCCESS: Tap In successful', $response->getContent());

        // Clear cache to bypass anti-duplicate throttling (2s lockout)
        \Illuminate\Support\Facades\Cache::flush();

        // Travel forward in time (10 seconds) to bypass tap-too-fast interval check (5 seconds)
        $this->travel(10)->seconds();

        // 2. Send POST request to /api/test-tap (Tap Out)
        $response = $this->post('/api/test-tap');
        $response->assertStatus(200);
        $this->assertStringContainsString('SUCCESS: Tap Out successful', $response->getContent());
    }

    public function test_gps_route_accepts_get_and_post()
    {
        // 1. Send GET request to /api/gps (Tap In)
        $response = $this->get('/api/gps');
        $response->assertStatus(200);
        $this->assertStringContainsString('SUCCESS: Tap In successful', $response->getContent());

        // Clear cache to bypass anti-duplicate throttling (2s lockout)
        \Illuminate\Support\Facades\Cache::flush();

        // Travel forward in time (10 seconds) to bypass tap-too-fast interval check (5 seconds)
        $this->travel(10)->seconds();

        // 2. Send POST request to /api/gps (Tap Out)
        $response = $this->post('/api/gps');
        $response->assertStatus(200);
        $this->assertStringContainsString('SUCCESS: Tap Out successful', $response->getContent());
    }
}
