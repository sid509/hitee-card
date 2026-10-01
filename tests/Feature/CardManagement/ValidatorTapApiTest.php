<?php

namespace Tests\Feature\CardManagement;

use App\Models\BalanceIn;
use App\Models\BalanceOut;
use App\Models\Bus;
use App\Models\Card;
use App\Models\Fare;
use App\Models\FareMatrix;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Stop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ValidatorTapApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Card $card;
    private Bus $bus;
    private array $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // Rs 100.00 top-up in MAJOR units — the ledger convention.
        BalanceIn::create([
            'user_id' => $this->user->id,
            'amount' => 100,
            'type' => 'manual',
            'status' => 'completed',
            'gateway_name' => 'Test',
            'transaction_id' => 'TXN-1',
        ]);

        $this->card = Card::create([
            'user_id' => $this->user->id,
            'card_uid' => 'A1B2C3D4',
            'card_number' => '1122334455',
            'hitee_card_number' => '1122334455',
            'status' => 'ACTIVE',
        ]);

        $route = Route::create(['name' => 'R1', 'direction' => 'inbound']);
        $stopA = Stop::create(['name' => 'Stop A', 'latitude' => 27.7, 'longitude' => 85.3]);
        $stopB = Stop::create(['name' => 'Stop B', 'latitude' => 27.71, 'longitude' => 85.31]);
        RouteStop::create(['route_id' => $route->id, 'stop_id' => $stopA->id, 'order' => 1, 'stop_name' => 'Stop A', 'latitude' => 27.7, 'longitude' => 85.3]);
        RouteStop::create(['route_id' => $route->id, 'stop_id' => $stopB->id, 'order' => 2, 'stop_name' => 'Stop B', 'latitude' => 27.71, 'longitude' => 85.31]);

        // Validator device_id doubles as the bus hwid.
        $this->bus = Bus::create([
            'name' => 'Bus 1',
            'bus_number' => 'BA 1 PA 1111',
            'hwid' => 'E60-TAP-TEST',
            'status' => 'ACTIVE',
            'route_id' => $route->id,
        ]);

        $fare = Fare::create([
            'name' => 'Fare 1',
            'route_id' => $route->id,
            'status' => 'approved',
            'effective_from' => now(),
        ]);
        FareMatrix::create([
            'fare_id' => $fare->id,
            'from_stop_id' => $stopA->id,
            'to_stop_id' => $stopB->id,
            'amount' => 25, // Rs 25.00 major units
        ]);
        $this->bus->update(['active_fare_id' => $fare->id]);

        $response = $this->postJson('/api/v1/devices/register', [
            'deviceId' => 'E60-TAP-TEST',
            'vehicleId' => 'BUS-042',
            'routeId' => 'ROUTE-7',
            'terminalNumber' => '393230303031',
        ]);
        $response->assertCreated();
        $this->device = $response->json('data.device');
    }

    private function tap(array $overrides = [], ?string $token = null)
    {
        return $this->postJson('/api/v1/validator/tap', array_merge([
            'card_uid' => 'A1B2C3D4',
            'device_id' => 'E60-TAP-TEST',
            'lat' => 27.7,
            'lon' => 85.3,
        ], $overrides), $token === null
            ? ['Authorization' => 'Bearer '.$this->device['apiToken']]
            : ($token === '' ? [] : ['Authorization' => 'Bearer '.$token]));
    }

    public function test_validator_tap_requires_device_token(): void
    {
        $this->tap(token: '')->assertStatus(401);
        $this->tap(token: 'bogus-token')->assertStatus(401);
    }

    public function test_tap_in_returns_minor_unit_balance(): void
    {
        $response = $this->tap();

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'in')
            ->assertJsonPath('data.card_number', '1122334455')
            // Rs 100 wallet → 10000 minor units on the wire.
            ->assertJsonPath('data.balance', 10000);
    }

    public function test_tap_out_debits_major_units_in_ledger_and_minor_on_wire(): void
    {
        $this->tap()->assertOk();

        Cache::flush();
        $this->travel(6)->seconds();

        $response = $this->tap(['lat' => 27.71, 'lon' => 85.31]);

        $response->assertOk()
            ->assertJsonPath('data.type', 'out')
            // Wire contract is minor units: Rs 25 → 2500.
            ->assertJsonPath('data.fare', 2500)
            ->assertJsonPath('data.balance_before', 10000)
            ->assertJsonPath('data.balance_after', 7500);

        // The LEDGER stores major units — this is the regression guard
        // against the old code path that wrote 2500 into `amount`.
        $this->assertDatabaseHas('balance_outs', [
            'card_id' => $this->card->id,
            'amount' => 25,
            'type' => 'fare_deduction',
        ]);
        $this->assertEquals(75, $this->user->fresh()->balance());
    }

    public function test_card_number_mismatch_is_rejected(): void
    {
        $this->tap(['card_number' => '9999999999'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'CARD_NUMBER_MISMATCH');
    }

    public function test_inactive_card_is_rejected(): void
    {
        $this->card->update(['status' => 'BLOCKED']);

        $this->tap()
            ->assertForbidden()
            ->assertJsonPath('error.code', 'CARD_INACTIVE');
    }

    public function test_insufficient_balance_returns_minor_unit_details(): void
    {
        // Drain the wallet: card must have < Rs 15 (min-fare fallback).
        BalanceOut::create([
            'user_id' => $this->user->id,
            'amount' => 95,
            'type' => 'manual',
        ]);

        $response = $this->tap();

        $response->assertStatus(402)
            ->assertJsonPath('error.code', 'INSUFFICIENT_BALANCE');
        $this->assertEquals(500, $response->json('error.balance'));
    }
}
