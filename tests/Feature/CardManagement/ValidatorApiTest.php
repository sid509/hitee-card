<?php

namespace Tests\Feature\CardManagement;

use App\Models\BlocklistEntry;
use App\Models\ValidatorDevice;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ValidatorApiTest extends TestCase
{
    use RefreshDatabase;

    private function registerDevice(): array
    {
        $response = $this->postJson('/api/v1/devices/register', [
            'deviceId' => 'E60-TEST-001',
            'vehicleId' => 'BUS-042',
            'routeId' => 'ROUTE-7',
            'terminalNumber' => '393230303031',
        ]);

        $response->assertCreated();
        $data = $response->json('data.device');
        return [
            'id' => $data['id'],
            'deviceId' => $data['deviceId'],
            'apiToken' => $data['apiToken'],
        ];
    }

    private function authHeaders(string $token): array
    {
        return [
            'Authorization' => "Bearer $token",
            'x-workstation-id' => 'ws-test',
        ];
    }

    public function test_register_device(): void
    {
        $response = $this->postJson('/api/v1/devices/register', [
            'deviceId' => 'E60-TEST-001',
            'vehicleId' => 'BUS-042',
            'routeId' => 'ROUTE-7',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.device.deviceId', 'E60-TEST-001')
            ->assertJsonPath('data.device.status', 'ACTIVE')
            ->assertJsonStructure(['data' => ['device' => ['apiToken']]]);
    }

    public function test_register_device_re_registration_updates(): void
    {
        // First registration
        $first = $this->postJson('/api/v1/devices/register', [
            'deviceId' => 'E60-TEST-001',
            'vehicleId' => 'BUS-042',
        ]);

        $firstToken = $first->json('data.device.apiToken');

        // Re-registration without credentials must be rejected — otherwise
        // anyone could claim an existing deviceId and rotate its token.
        $unauthorized = $this->postJson('/api/v1/devices/register', [
            'deviceId' => 'E60-TEST-001',
            'vehicleId' => 'BUS-099',
        ]);
        $unauthorized->assertStatus(401)
            ->assertJsonPath('error.code', 'DEVICE_UNAUTHORIZED');

        // Re-registration with the current device token updates fields and
        // preserves the token (apiToken null → no rotation).
        $second = $this->withHeaders(['Authorization' => "Bearer {$firstToken}"])
            ->postJson('/api/v1/devices/register', [
                'deviceId' => 'E60-TEST-001',
                'vehicleId' => 'BUS-099',
            ]);

        $second->assertCreated()
            ->assertJsonPath('data.device.vehicleId', 'BUS-099')
            ->assertJsonPath('data.device.apiToken', null);

        // Explicit rotation issues a new token.
        $rotated = $this->withHeaders(['Authorization' => "Bearer {$firstToken}"])
            ->postJson('/api/v1/devices/register', [
                'deviceId' => 'E60-TEST-001',
                'rotateToken' => true,
            ]);

        $rotated->assertCreated();
        $newToken = $rotated->json('data.device.apiToken');
        $this->assertNotNull($newToken);
        $this->assertNotEquals($firstToken, $newToken);
    }

    public function test_heartbeat_requires_auth(): void
    {
        $response = $this->postJson('/api/v1/devices/heartbeat');
        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'DEVICE_UNAUTHORIZED');
    }

    public function test_heartbeat_success(): void
    {
        $device = $this->registerDevice();

        $response = $this->withHeaders($this->authHeaders($device['apiToken']))
            ->postJson('/api/v1/devices/heartbeat');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.device.status', 'ACTIVE');
    }

    public function test_sync_trips_success(): void
    {
        $device = $this->registerDevice();

        $response = $this->withHeaders($this->authHeaders($device['apiToken']))
            ->postJson("/api/v1/devices/{$device['deviceId']}/trips/sync", [
                'trips' => [
                    [
                        'tripId' => 'TRIP-001',
                        'cardUid' => 'A1B2C3D4',
                        'tapInAt' => '2026-08-25T10:00:00Z',
                        'tapInLat' => 27.7172,
                        'tapInLng' => 85.3240,
                        'tapOutAt' => '2026-08-25T10:30:00Z',
                        'tapOutLat' => 27.7272,
                        'tapOutLng' => 85.3340,
                        'distanceMeters' => 1500,
                        'fareMinorUnits' => 500,
                        'balanceBefore' => 10000,
                        'balanceAfter' => 9500,
                        'offlineCounter' => 42,
                        'transactionDateTime' => '20260825103000',
                        'terminalTransactionSequence' => '42',
                        'reconciliationStatus' => 'none',
                        'idempotencyKey' => 'trip-key-001',
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.synced', 1)
            ->assertJsonPath('data.duplicates', 0)
            ->assertJsonPath('data.results.0.status', 'CREATED');
    }

    public function test_sync_trips_idempotency(): void
    {
        $device = $this->registerDevice();
        $headers = $this->authHeaders($device['apiToken']);
        $url = "/api/v1/devices/{$device['deviceId']}/trips/sync";

        $trip = [
            'trips' => [
                [
                    'tripId' => 'TRIP-001',
                    'cardUid' => 'A1B2C3D4',
                    'fareMinorUnits' => 500,
                    'idempotencyKey' => 'trip-key-unique-001',
                ],
            ],
        ];

        // First sync
        $first = $this->withHeaders($headers)->postJson($url, $trip);
        $first->assertOk()->assertJsonPath('data.synced', 1);

        // Second sync with same idempotency key
        $second = $this->withHeaders($headers)->postJson($url, $trip);
        $second->assertOk()
            ->assertJsonPath('data.synced', 0)
            ->assertJsonPath('data.duplicates', 1)
            ->assertJsonPath('data.results.0.status', 'DUPLICATE');
    }

    public function test_sync_trips_requires_auth(): void
    {
        $response = $this->postJson('/api/v1/devices/E60-TEST-001/trips/sync', [
            'trips' => [],
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'DEVICE_UNAUTHORIZED');
    }

    public function test_blocklist_delta_empty(): void
    {
        $device = $this->registerDevice();

        $response = $this->withHeaders($this->authHeaders($device['apiToken']))
            ->getJson('/api/v1/blocklist/delta');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.count', 0)
            ->assertJsonPath('data.entries', []);
    }

    public function test_blocklist_delta_returns_entries(): void
    {
        $device = $this->registerDevice();

        BlocklistEntry::create([
            'card_uid' => 'AABBCCDD',
            'reason' => 'STOLEN',
            'status' => 'ACTIVE',
            'source' => 'MANUAL',
        ]);

        $response = $this->withHeaders($this->authHeaders($device['apiToken']))
            ->getJson('/api/v1/blocklist/delta');

        $response->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.entries.0.cardUid', 'AABBCCDD')
            ->assertJsonPath('data.entries.0.reason', 'STOLEN');
    }

    public function test_blocklist_status_unblocked(): void
    {
        $device = $this->registerDevice();
        $response = $this->withHeaders($this->authHeaders($device['apiToken']))
            ->getJson('/api/v1/cards/A1B2C3D4/blocklist-status');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.blocked', false);
    }

    public function test_blocklist_status_blocked(): void
    {
        $device = $this->registerDevice();
        BlocklistEntry::create([
            'card_uid' => 'AABBCCDD',
            'reason' => 'FRAUD',
            'status' => 'ACTIVE',
            'source' => 'SYSTEM',
        ]);

        $response = $this->withHeaders($this->authHeaders($device['apiToken']))
            ->getJson('/api/v1/cards/AABBCCDD/blocklist-status');

        $response->assertOk()
            ->assertJsonPath('data.blocked', true)
            ->assertJsonPath('data.reason', 'FRAUD');
    }

    public function test_blocklist_status_ignores_lifted_entries(): void
    {
        $device = $this->registerDevice();
        BlocklistEntry::create([
            'card_uid' => 'AABBCCDD',
            'reason' => 'STOLEN',
            'status' => 'LIFTED',
            'lifted_at' => now(),
            'source' => 'MANUAL',
        ]);

        $response = $this->withHeaders($this->authHeaders($device['apiToken']))
            ->getJson('/api/v1/cards/AABBCCDD/blocklist-status');

        $response->assertOk()
            ->assertJsonPath('data.blocked', false);
    }

    public function test_sync_trips_batch(): void
    {
        $device = $this->registerDevice();

        $trips = [];
        for ($i = 1; $i <= 5; $i++) {
            $trips[] = [
                'tripId' => "TRIP-$i",
                'cardUid' => 'A1B2C3D4',
                'fareMinorUnits' => 500,
                'idempotencyKey' => "batch-key-$i",
            ];
        }

        $response = $this->withHeaders($this->authHeaders($device['apiToken']))
            ->postJson("/api/v1/devices/{$device['deviceId']}/trips/sync", [
                'trips' => $trips,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.synced', 5)
            ->assertJsonPath('data.duplicates', 0);
    }
}
