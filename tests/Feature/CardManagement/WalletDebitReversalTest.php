<?php

namespace Tests\Feature\CardManagement;

use App\Models\Card;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class WalletDebitReversalTest extends TestCase
{
    use RefreshDatabase;

    private function headers(): array
    {
        return ['x-workstation-id' => 'ws-test'];
    }

    private function registerCard(): Card
    {
        $this->withHeaders($this->headers())->postJson('/api/v1/cards/register', [
            'uid' => 'DEADFBEB',
            'cardNumber' => '9999000000000003',
            'environment' => 'LAB',
        ]);

        return Card::where('card_uid', 'DEADFBEB')->firstOrFail();
    }

    public function test_wallet_create_recharge(): void
    {
        $this->registerCard();

        $response = $this->withHeaders($this->headers())->postJson('/api/v1/wallet/cards/DEADFBEB/recharges', [
            'amountMinorUnits' => 5000,
            'idempotencyKey' => 'recharge-key-001',
            'terminalNumberHex' => '393230303031',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.operation.status', 'KEYS_PENDING')
            ->assertJsonPath('data.operation.amountMinorUnits', 5000);
    }

    public function test_wallet_create_recharge_idempotency(): void
    {
        $this->registerCard();
        $payload = [
            'amountMinorUnits' => 5000,
            'idempotencyKey' => 'recharge-key-idem-001',
            'terminalNumberHex' => '393230303031',
        ];

        $first = $this->withHeaders($this->headers())->postJson('/api/v1/wallet/cards/DEADFBEB/recharges', $payload);
        $first->assertCreated();

        $second = $this->withHeaders($this->headers())->postJson('/api/v1/wallet/cards/DEADFBEB/recharges', $payload);
        $second->assertCreated();
        // Same operation ID returned (idempotent)
        $this->assertEquals($first->json('data.operation.id'), $second->json('data.operation.id'));
    }

    public function test_wallet_get_recharge(): void
    {
        $this->registerCard();

        $create = $this->withHeaders($this->headers())->postJson('/api/v1/wallet/cards/DEADFBEB/recharges', [
            'amountMinorUnits' => 3000,
            'idempotencyKey' => 'recharge-key-002',
            'terminalNumberHex' => '393230303031',
        ]);
        $operationId = $create->json('data.operation.id');

        $response = $this->withHeaders($this->headers())->getJson("/api/v1/wallet/recharges/{$operationId}");

        $response->assertOk()
            ->assertJsonPath('data.operation.id', $operationId);
    }

    public function test_wallet_history_by_card(): void
    {
        $this->registerCard();

        $this->withHeaders($this->headers())->postJson('/api/v1/wallet/cards/DEADFBEB/recharges', [
            'amountMinorUnits' => 1000,
            'idempotencyKey' => 'recharge-key-003',
            'terminalNumberHex' => '393230303031',
        ]);

        $response = $this->withHeaders($this->headers())->getJson('/api/v1/wallet/cards/DEADFBEB/recharges');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.operations.0.amountMinorUnits', 1000);
    }

    public function test_wallet_checkpoint(): void
    {
        $this->registerCard();

        $create = $this->withHeaders($this->headers())->postJson('/api/v1/wallet/cards/DEADFBEB/recharges', [
            'amountMinorUnits' => 2000,
            'idempotencyKey' => 'recharge-key-004',
            'terminalNumberHex' => '393230303031',
        ]);
        $operationId = $create->json('data.operation.id');

        $response = $this->withHeaders($this->headers())->postJson("/api/v1/wallet/recharges/{$operationId}/checkpoints", [
            'step' => 'BALANCE_READ_BEFORE',
            'metadata' => ['balance' => 100],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_wallet_cancel(): void
    {
        $this->registerCard();

        $create = $this->withHeaders($this->headers())->postJson('/api/v1/wallet/cards/DEADFBEB/recharges', [
            'amountMinorUnits' => 2000,
            'idempotencyKey' => 'recharge-key-005',
            'terminalNumberHex' => '393230303031',
        ]);
        $operationId = $create->json('data.operation.id');
        $version = $create->json('data.operation.lockVersion');

        $response = $this->withHeaders($this->headers())->postJson("/api/v1/wallet/recharges/{$operationId}/cancel", [
            'expectedVersion' => $version,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.operation.status', 'CANCELLED');
    }

    public function test_debit_create_operation(): void
    {
        $this->registerCard();

        $response = $this->withHeaders($this->headers())->postJson('/api/v1/debit/cards/DEADFBEB/debits', [
            'amountMinorUnits' => 500,
            'idempotencyKey' => 'debit-key-001',
            'terminalNumberHex' => '393230303031',
            'terminalTransactionSequence' => 1,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.operation.status', 'KEYS_PENDING');
    }

    public function test_debit_get_operation(): void
    {
        $this->registerCard();

        $create = $this->withHeaders($this->headers())->postJson('/api/v1/debit/cards/DEADFBEB/debits', [
            'amountMinorUnits' => 500,
            'idempotencyKey' => 'debit-key-002',
            'terminalNumberHex' => '393230303031',
            'terminalTransactionSequence' => 2,
        ]);
        $operationId = $create->json('data.operation.id');

        $response = $this->withHeaders($this->headers())->getJson("/api/v1/debit/operations/{$operationId}");

        $response->assertOk()
            ->assertJsonPath('data.operation.id', $operationId);
    }

    public function test_debit_history_by_card(): void
    {
        $this->registerCard();

        $this->withHeaders($this->headers())->postJson('/api/v1/debit/cards/DEADFBEB/debits', [
            'amountMinorUnits' => 500,
            'idempotencyKey' => 'debit-key-003',
            'terminalNumberHex' => '393230303031',
            'terminalTransactionSequence' => 3,
        ]);

        $response = $this->withHeaders($this->headers())->getJson('/api/v1/debit/cards/DEADFBEB/debits');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.operations.0.amountMinorUnits', 500);
    }

    public function test_debit_checkpoint(): void
    {
        $this->registerCard();

        $create = $this->withHeaders($this->headers())->postJson('/api/v1/debit/cards/DEADFBEB/debits', [
            'amountMinorUnits' => 500,
            'idempotencyKey' => 'debit-key-004',
            'terminalNumberHex' => '393230303031',
            'terminalTransactionSequence' => 4,
        ]);
        $operationId = $create->json('data.operation.id');

        $response = $this->withHeaders($this->headers())->postJson("/api/v1/debit/operations/{$operationId}/checkpoints", [
            'step' => 'BALANCE_READ_BEFORE',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_debit_fail(): void
    {
        $this->registerCard();

        $create = $this->withHeaders($this->headers())->postJson('/api/v1/debit/cards/DEADFBEB/debits', [
            'amountMinorUnits' => 500,
            'idempotencyKey' => 'debit-key-005',
            'terminalNumberHex' => '393230303031',
            'terminalTransactionSequence' => 5,
        ]);
        $operationId = $create->json('data.operation.id');
        $version = $create->json('data.operation.lockVersion');

        $response = $this->withHeaders($this->headers())->postJson("/api/v1/debit/operations/{$operationId}/fail", [
            'expectedVersion' => $version,
            'failureCode' => 'CARD_REMOVED',
            'failureMessage' => 'Card removed during debit',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.operation.status', 'FAILED')
            ->assertJsonPath('data.operation.failureCode', 'CARD_REMOVED');
    }

    public function test_reversal_configuration(): void
    {
        $response = $this->withHeaders($this->headers())->getJson('/api/v1/reversal/configuration');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.labRechargeReversalWritesEnabled', true);
    }

    public function test_version_conflict_on_stale_update(): void
    {
        $this->registerCard();

        $create = $this->withHeaders($this->headers())->postJson('/api/v1/wallet/cards/DEADFBEB/recharges', [
            'amountMinorUnits' => 2000,
            'idempotencyKey' => 'recharge-key-version-test',
            'terminalNumberHex' => '393230303031',
        ]);
        $operationId = $create->json('data.operation.id');

        // Use wrong expected version
        $response = $this->withHeaders($this->headers())->postJson("/api/v1/wallet/recharges/{$operationId}/cancel", [
            'expectedVersion' => 99,
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'VERSION_CONFLICT');
    }
}
