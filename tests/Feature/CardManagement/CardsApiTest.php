<?php

namespace Tests\Feature\CardManagement;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CardsApiTest extends TestCase
{
    use RefreshDatabase;

    private function headers(): array
    {
        return [
            'x-workstation-id' => 'ws-test-001',
            'x-operator-id' => 'op-test-001',
            'x-request-id' => \Illuminate\Support\Str::uuid()->toString(),
        ];
    }

    public function test_check_unregistered_card(): void
    {
        $response = $this->withHeaders($this->headers())
            ->postJson('/api/v1/cards/check', ['uid' => 'A1B2C3D4']);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.registered', false)
            ->assertJsonPath('data.card', null);
    }

    public function test_register_card(): void
    {
        $response = $this->withHeaders($this->headers())
            ->postJson('/api/v1/cards/register', [
                'uid' => 'A1B2C3D4',
                'remarks' => 'Test card',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.card.uid', 'A1B2C3D4')
            ->assertJsonPath('data.card.status', 'REGISTERED')
            ->assertJsonPath('data.card.environment', 'LAB');
    }

    public function test_register_duplicate_card_returns_conflict(): void
    {
        $this->withHeaders($this->headers())
            ->postJson('/api/v1/cards/register', ['uid' => 'A1B2C3D4']);

        $response = $this->withHeaders($this->headers())
            ->postJson('/api/v1/cards/register', ['uid' => 'A1B2C3D4']);

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'CARD_ALREADY_REGISTERED');
    }

    public function test_get_card_by_uid(): void
    {
        $this->withHeaders($this->headers())
            ->postJson('/api/v1/cards/register', ['uid' => 'A1B2C3D4']);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/cards/A1B2C3D4');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.card.uid', 'A1B2C3D4');
    }

    public function test_get_card_not_found(): void
    {
        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/cards/FFFFFFFF');

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'CARD_NOT_REGISTERED');
    }

    public function test_missing_workstation_header_returns_400(): void
    {
        $response = $this->postJson('/api/v1/cards/check', ['uid' => 'A1B2C3D4']);

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'MISSING_WORKSTATION_ID');
    }

    public function test_assign_lab_profile(): void
    {
        $this->withHeaders($this->headers())
            ->postJson('/api/v1/cards/register', ['uid' => 'A1B2C3D4']);

        $response = $this->withHeaders($this->headers())
            ->postJson('/api/v1/cards/A1B2C3D4/assign-lab-profile', []);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.card.keyProfileVersion', 'hitee-lab-v1');
    }

    public function test_create_initialization_operation(): void
    {
        $this->withHeaders($this->headers())
            ->postJson('/api/v1/cards/register', ['uid' => 'A1B2C3D4']);

        $response = $this->withHeaders(array_merge($this->headers(), [
            'idempotency-key' => \Illuminate\Support\Str::uuid()->toString(),
        ]))
            ->postJson('/api/v1/cards/A1B2C3D4/initialization-operations', ['mode' => 'INITIALIZE']);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.operation.status', 'KEYS_PENDING')
            ->assertJsonPath('data.operation.mode', 'INITIALIZE')
            ->assertJsonPath('data.operation.version', 1);
    }

    public function test_initialization_configuration(): void
    {
        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/initialization-operations/configuration');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.environment', 'LAB')
            ->assertJsonPath('data.physicalInitializationEnabled', true)
            ->assertJsonPath('data.secureKeyEnvelopeBridgeEnabled', true)
            ->assertJsonPath('data.labProfile.profileId', 'hitee-lab-v1');
    }

    public function test_wallet_configuration(): void
    {
        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/wallet/configuration');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.labRechargeWritesEnabled', true)
            ->assertJsonPath('data.maxAmountMinorUnits', 100000);
    }

    public function test_debit_configuration(): void
    {
        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/debit/configuration');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.labDebitWritesEnabled', true)
            ->assertJsonPath('data.maxAmountMinorUnits', 50000);
    }

    public function test_reversal_configuration(): void
    {
        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/reversal/configuration');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.labRechargeReversalWritesEnabled', true);
    }

    public function test_issuance_configuration(): void
    {
        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/issuance/configuration');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ready', true)
            ->assertJsonPath('data.profileSnapshot.cityCode', '0000000000000001');
    }

    public function test_replacement_configuration(): void
    {
        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/replacement/configuration');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ready', true);
    }

    public function test_recovery_items_empty(): void
    {
        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/recovery/items');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items', []);
    }

    public function test_audit_events_list(): void
    {
        // Generate an audit event by registering a card
        $this->withHeaders($this->headers())
            ->postJson('/api/v1/cards/register', ['uid' => 'A1B2C3D4']);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/audit/events?limit=10');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['events', 'nextCursor']]);
    }

    public function test_receipts_invalid_operation_type(): void
    {
        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/receipts/INVALID_TYPE/some-id');

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'INVALID_OPERATION_TYPE');
    }

    public function test_response_envelope_format(): void
    {
        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/wallet/configuration');

        $json = $response->json();
        $this->assertArrayHasKey('success', $json);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayNotHasKey('status', $json);
        $this->assertArrayNotHasKey('message', $json);
        $this->assertArrayNotHasKey('content', $json);
    }
}
