<?php

namespace Tests\Feature\CardManagement;

use App\Models\CardManagement\ValidatorDevice;
use App\Models\CardManagement\ValidatorTrip;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class SettlementTest extends TestCase
{
    use RefreshDatabase;

    private function headers(): array
    {
        return ['x-workstation-id' => 'ws-test'];
    }

    private function createDeviceAndTrips(string $date): array
    {
        // Register a device
        $device = ValidatorDevice::create([
            'id' => Str::uuid()->toString(),
            'device_id' => 'E60-SETTLE-001',
            'vehicle_id' => 'BUS-042',
            'route_id' => 'ROUTE-7',
            'status' => 'ACTIVE',
            'api_token' => hash('sha256', Str::random(64)),
        ]);

        // Create some trips for the date
        for ($i = 1; $i <= 5; $i++) {
            ValidatorTrip::create([
                'id' => Str::uuid()->toString(),
                'device_id' => $device->id,
                'trip_id' => "TRIP-$i",
                'card_uid' => 'A1B2C3D4',
                'tap_in_at' => "$date 10:00:00",
                'tap_out_at' => "$date 10:30:00",
                'fare_minor_units' => 500,
                'reconciliation_status' => 'success',
                'sync_status' => 'SYNCED',
                'idempotency_key' => "settle-trip-$i",
            ]);
        }

        return [$device];
    }

    public function test_run_settlement(): void
    {
        $this->createDeviceAndTrips('2026-08-24');

        $response = $this->withHeaders($this->headers())->postJson('/api/v1/settlement/run', [
            'date' => '2026-08-24',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.batch.status', 'CALCULATED')
            ->assertJsonPath('data.batch.totalTrips', 5)
            ->assertJsonPath('data.batch.totalFareMinorUnits', 2500);
    }

    public function test_settlement_idempotency(): void
    {
        $this->createDeviceAndTrips('2026-08-24');

        $first = $this->withHeaders($this->headers())->postJson('/api/v1/settlement/run', [
            'date' => '2026-08-24',
        ]);
        $first->assertCreated();

        $second = $this->withHeaders($this->headers())->postJson('/api/v1/settlement/run', [
            'date' => '2026-08-24',
        ]);

        // Same batch ID returned (idempotent)
        $this->assertEquals($first->json('data.batch.id'), $second->json('data.batch.id'));
    }

    public function test_settlement_commission_calculation(): void
    {
        $this->createDeviceAndTrips('2026-08-24');

        $response = $this->withHeaders($this->headers())->postJson('/api/v1/settlement/run', [
            'date' => '2026-08-24',
        ]);

        // 5 trips × 500 = 2500 fare
        // 10% commission = 250
        // Payout = 2500 - 250 = 2250
        $response->assertCreated()
            ->assertJsonPath('data.batch.totalCommissionMinorUnits', 250)
            ->assertJsonPath('data.batch.totalPayoutMinorUnits', 2250);
    }

    public function test_settlement_approve(): void
    {
        $this->createDeviceAndTrips('2026-08-24');

        $run = $this->withHeaders($this->headers())->postJson('/api/v1/settlement/run', [
            'date' => '2026-08-24',
        ]);
        $batchId = $run->json('data.batch.id');

        $response = $this->withHeaders($this->headers())->postJson("/api/v1/settlement/batches/{$batchId}/approve");

        $response->assertOk()
            ->assertJsonPath('data.batch.status', 'APPROVED');
    }

    public function test_settlement_pay(): void
    {
        $this->createDeviceAndTrips('2026-08-24');

        $run = $this->withHeaders($this->headers())->postJson('/api/v1/settlement/run', [
            'date' => '2026-08-24',
        ]);
        $batchId = $run->json('data.batch.id');

        $this->withHeaders($this->headers())->postJson("/api/v1/settlement/batches/{$batchId}/approve");
        $response = $this->withHeaders($this->headers())->postJson("/api/v1/settlement/batches/{$batchId}/pay");

        $response->assertOk()
            ->assertJsonPath('data.batch.status', 'PAID');
    }

    public function test_settlement_close(): void
    {
        $this->createDeviceAndTrips('2026-08-24');

        $run = $this->withHeaders($this->headers())->postJson('/api/v1/settlement/run', [
            'date' => '2026-08-24',
        ]);
        $batchId = $run->json('data.batch.id');

        $this->withHeaders($this->headers())->postJson("/api/v1/settlement/batches/{$batchId}/approve");
        $this->withHeaders($this->headers())->postJson("/api/v1/settlement/batches/{$batchId}/pay");
        $response = $this->withHeaders($this->headers())->postJson("/api/v1/settlement/batches/{$batchId}/close");

        $response->assertOk()
            ->assertJsonPath('data.batch.status', 'CLOSED');
    }

    public function test_settlement_list_batches(): void
    {
        $this->createDeviceAndTrips('2026-08-24');

        $this->withHeaders($this->headers())->postJson('/api/v1/settlement/run', [
            'date' => '2026-08-24',
        ]);

        $response = $this->withHeaders($this->headers())->getJson('/api/v1/settlement/batches');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.batches.0.totalTrips', 5);
    }

    public function test_settlement_payout_file(): void
    {
        $this->createDeviceAndTrips('2026-08-24');

        $run = $this->withHeaders($this->headers())->postJson('/api/v1/settlement/run', [
            'date' => '2026-08-24',
        ]);
        $batchId = $run->json('data.batch.id');

        $response = $this->withHeaders($this->headers())->get("/api/v1/settlement/batches/{$batchId}/payout-file");

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->getContent();
        $this->assertStringContainsString('BatchID', $content);
        $this->assertStringContainsString('DeviceID', $content);
        $this->assertStringContainsString('2500', $content);
    }

    public function test_settlement_cannot_approve_twice(): void
    {
        $this->createDeviceAndTrips('2026-08-24');

        $run = $this->withHeaders($this->headers())->postJson('/api/v1/settlement/run', [
            'date' => '2026-08-24',
        ]);
        $batchId = $run->json('data.batch.id');

        $this->withHeaders($this->headers())->postJson("/api/v1/settlement/batches/{$batchId}/approve");
        $second = $this->withHeaders($this->headers())->postJson("/api/v1/settlement/batches/{$batchId}/approve");

        $second->assertStatus(409)
            ->assertJsonPath('error.code', 'INVALID_STATE_TRANSITION');
    }

    public function test_settlement_cannot_pay_without_approval(): void
    {
        $this->createDeviceAndTrips('2026-08-24');

        $run = $this->withHeaders($this->headers())->postJson('/api/v1/settlement/run', [
            'date' => '2026-08-24',
        ]);
        $batchId = $run->json('data.batch.id');

        $response = $this->withHeaders($this->headers())->postJson("/api/v1/settlement/batches/{$batchId}/pay");

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'INVALID_STATE_TRANSITION');
    }
}
