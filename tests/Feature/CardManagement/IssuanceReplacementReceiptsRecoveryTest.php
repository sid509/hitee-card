<?php

namespace Tests\Feature\CardManagement;

use App\Models\Card;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class IssuanceReplacementReceiptsRecoveryTest extends TestCase
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

    // ── Issuance ──────────────────────────────────────────────────

    public function test_issuance_create_customer(): void
    {
        $response = $this->withHeaders($this->headers())->postJson('/api/v1/issuance/customers', [
            'customerNumber' => 'CUST-001',
            'customerType' => 'NAMED',
            'fullName' => 'John Doe',
            'phone' => '+1234567890',
            'email' => 'john@example.com',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.customer.customerNumber', 'CUST-001')
            ->assertJsonPath('data.customer.fullName', 'John Doe');
    }

    public function test_issuance_search_customers(): void
    {
        $this->withHeaders($this->headers())->postJson('/api/v1/issuance/customers', [
            'customerNumber' => 'CUST-002',
            'fullName' => 'Jane Smith',
        ]);

        $response = $this->withHeaders($this->headers())->getJson('/api/v1/issuance/customers?q=Jane');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.customers.0.fullName', 'Jane Smith');
    }

    public function test_issuance_create_operation(): void
    {
        $this->registerCard();

        $response = $this->withHeaders($this->headers())->postJson('/api/v1/issuance/cards/DEADFBEB/operations', [
            'idempotencyKey' => 'issuance-key-001',
            'issuanceMode' => 'NEW',
            'cardCategory' => 'STANDARD',
            'cardTypeCode' => '0001',
            'applicationType' => '01',
            'depositMinorUnits' => 1000,
            'enableDate' => '2026-01-01',
            'expiryDate' => '2029-01-01',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.operation.status', 'KEYS_PENDING');
    }

    public function test_issuance_get_history(): void
    {
        $this->registerCard();

        $this->withHeaders($this->headers())->postJson('/api/v1/issuance/cards/DEADFBEB/operations', [
            'idempotencyKey' => 'issuance-key-002',
            'issuanceMode' => 'NEW',
            'cardCategory' => 'STANDARD',
            'cardTypeCode' => '0001',
            'applicationType' => '01',
            'enableDate' => '2026-01-01',
            'expiryDate' => '2029-01-01',
        ]);

        $response = $this->withHeaders($this->headers())->getJson('/api/v1/issuance/cards/DEADFBEB/history');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.operations.0.status', 'KEYS_PENDING');
    }

    public function test_issuance_checkpoint(): void
    {
        $this->registerCard();

        $create = $this->withHeaders($this->headers())->postJson('/api/v1/issuance/cards/DEADFBEB/operations', [
            'idempotencyKey' => 'issuance-key-003',
            'issuanceMode' => 'NEW',
            'cardCategory' => 'STANDARD',
            'cardTypeCode' => '0001',
            'applicationType' => '01',
            'enableDate' => '2026-01-01',
            'expiryDate' => '2029-01-01',
        ]);
        $operationId = $create->json('data.operation.id');

        $response = $this->withHeaders($this->headers())->postJson("/api/v1/issuance/operations/{$operationId}/checkpoints", [
            'step' => 'CARD_PERSONALIZED',
            'metadata' => ['written' => true],
        ]);

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_issuance_complete_updates_card_status(): void
    {
        $this->registerCard();

        $create = $this->withHeaders($this->headers())->postJson('/api/v1/issuance/cards/DEADFBEB/operations', [
            'idempotencyKey' => 'issuance-key-complete',
            'issuanceMode' => 'NEW',
            'cardCategory' => 'STANDARD',
            'cardTypeCode' => '0001',
            'applicationType' => '01',
            'enableDate' => '2026-01-01',
            'expiryDate' => '2029-01-01',
        ]);
        $operationId = $create->json('data.operation.id');
        $version = $create->json('data.operation.lockVersion');

        $response = $this->withHeaders($this->headers())->postJson("/api/v1/issuance/operations/{$operationId}/complete", [
            'expectedVersion' => $version,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.operation.status', 'COMPLETED');

        // Card status should be ISSUED
        $card = Card::where('card_uid', 'DEADFBEB')->first();
        $this->assertEquals('ISSUED', $card->status);
    }

    public function test_issuance_lifecycle_action(): void
    {
        $this->registerCard();

        $response = $this->withHeaders($this->headers())->postJson('/api/v1/issuance/cards/DEADFBEB/lifecycle', [
            'action' => 'ACTIVATE',
            'reason' => 'Card activated for testing',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.card.status', 'ACTIVE');
    }

    public function test_issuance_lifecycle_history(): void
    {
        $this->registerCard();

        $this->withHeaders($this->headers())->postJson('/api/v1/issuance/cards/DEADFBEB/lifecycle', [
            'action' => 'ACTIVATE',
        ]);

        $response = $this->withHeaders($this->headers())->getJson('/api/v1/issuance/cards/DEADFBEB/lifecycle');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.events.0.toStatus', 'ACTIVE');
    }

    // ── Replacement ───────────────────────────────────────────────

    public function test_replacement_create_operation(): void
    {
        $this->registerCard();

        $response = $this->withHeaders($this->headers())->postJson('/api/v1/replacement/cards/DEADFBEB/operations', [
            'idempotencyKey' => 'replacement-key-001',
            'oldCardReference' => 'AABBCCDD',
            'reason' => 'LOST',
            'notes' => 'Customer reported lost card',
            'approvedTransferAmountMinorUnits' => 5000,
            'cardTypeCode' => '0001',
            'applicationType' => '01',
            'enableDate' => '2026-01-01',
            'expiryDate' => '2029-01-01',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.operation.status', 'CREATED');
    }

    public function test_replacement_get_operation(): void
    {
        $this->registerCard();

        $create = $this->withHeaders($this->headers())->postJson('/api/v1/replacement/cards/DEADFBEB/operations', [
            'idempotencyKey' => 'replacement-key-002',
            'oldCardReference' => 'AABBCCDD',
            'reason' => 'DAMAGED',
            'cardTypeCode' => '0001',
            'applicationType' => '01',
            'enableDate' => '2026-01-01',
            'expiryDate' => '2029-01-01',
        ]);
        $operationId = $create->json('data.operation.id');

        $response = $this->withHeaders($this->headers())->getJson("/api/v1/replacement/operations/{$operationId}");

        $response->assertOk()
            ->assertJsonPath('data.operation.id', $operationId);
    }

    public function test_replacement_record_balance_evidence(): void
    {
        $this->registerCard();

        $create = $this->withHeaders($this->headers())->postJson('/api/v1/replacement/cards/DEADFBEB/operations', [
            'idempotencyKey' => 'replacement-key-003',
            'oldCardReference' => 'AABBCCDD',
            'reason' => 'DAMAGED',
            'cardTypeCode' => '0001',
            'applicationType' => '01',
            'enableDate' => '2026-01-01',
            'expiryDate' => '2029-01-01',
        ]);
        $operationId = $create->json('data.operation.id');
        $version = $create->json('data.operation.lockVersion');

        $response = $this->withHeaders($this->headers())->postJson("/api/v1/replacement/operations/{$operationId}/balance-evidence", [
            'expectedVersion' => $version,
            'balanceMinorUnits' => 3000,
            'offlineCounter' => 42,
        ]);

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_replacement_history_by_card(): void
    {
        $this->registerCard();

        $this->withHeaders($this->headers())->postJson('/api/v1/replacement/cards/DEADFBEB/operations', [
            'idempotencyKey' => 'replacement-key-004',
            'oldCardReference' => 'AABBCCDD',
            'reason' => 'EXPIRED',
            'cardTypeCode' => '0001',
            'applicationType' => '01',
            'enableDate' => '2026-01-01',
            'expiryDate' => '2029-01-01',
        ]);

        $response = $this->withHeaders($this->headers())->getJson('/api/v1/replacement/cards/DEADFBEB/history');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.operations.0.status', 'CREATED');
    }

    // ── Receipts ──────────────────────────────────────────────────

    public function test_receipt_for_completed_wallet_recharge(): void
    {
        $this->registerCard();

        // Create and complete a wallet recharge
        $create = $this->withHeaders($this->headers())->postJson('/api/v1/wallet/cards/DEADFBEB/recharges', [
            'amountMinorUnits' => 5000,
            'idempotencyKey' => 'receipt-test-recharge',
            'terminalNumberHex' => '393230303031',
        ]);
        $operationId = $create->json('data.operation.id');
        $version = $create->json('data.operation.lockVersion');

        $this->withHeaders($this->headers())->postJson("/api/v1/wallet/recharges/{$operationId}/complete", [
            'expectedVersion' => $version,
        ]);

        $response = $this->withHeaders($this->headers())->getJson("/api/v1/receipts/RECHARGE/{$operationId}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.receipt.operationType', 'RECHARGE')
            ->assertJsonPath('data.receipt.operationStatus', 'COMPLETED')
            ->assertJsonPath('data.receipt.amountMinorUnits', 5000);
    }

    public function test_receipt_for_non_completed_returns_409(): void
    {
        $this->registerCard();

        $create = $this->withHeaders($this->headers())->postJson('/api/v1/wallet/cards/DEADFBEB/recharges', [
            'amountMinorUnits' => 5000,
            'idempotencyKey' => 'receipt-test-recharge-2',
            'terminalNumberHex' => '393230303031',
        ]);
        $operationId = $create->json('data.operation.id');

        $response = $this->withHeaders($this->headers())->getJson("/api/v1/receipts/RECHARGE/{$operationId}");

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'INVALID_STATE_TRANSITION');
    }

    public function test_receipt_invalid_type_returns_400(): void
    {
        $response = $this->withHeaders($this->headers())->getJson('/api/v1/receipts/INVALID/' . Str::uuid()->toString());

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_OPERATION_TYPE');
    }

    public function test_receipt_not_found_returns_404(): void
    {
        $response = $this->withHeaders($this->headers())->getJson('/api/v1/receipts/RECHARGE/' . Str::uuid()->toString());

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'OPERATION_NOT_FOUND');
    }

    // ── Recovery ──────────────────────────────────────────────────

    public function test_recovery_list_empty(): void
    {
        $response = $this->withHeaders($this->headers())->getJson('/api/v1/recovery/items');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.count', 0);
    }

    public function test_recovery_list_with_ambiguous_operation(): void
    {
        $this->registerCard();

        // Create a wallet recharge and leave it in CREDIT_PENDING_VERIFICATION
        $create = $this->withHeaders($this->headers())->postJson('/api/v1/wallet/cards/DEADFBEB/recharges', [
            'amountMinorUnits' => 5000,
            'idempotencyKey' => 'recovery-test-recharge',
            'terminalNumberHex' => '393230303031',
        ]);
        $operationId = $create->json('data.operation.id');
        $version = $create->json('data.operation.lockVersion');

        // Authorize first (need to go through state machine)
        // For this test, manually set status to AMBIGUOUS
        \Illuminate\Support\Facades\DB::table('wallet_recharge_operations')
            ->where('id', $operationId)
            ->update(['status' => 'AMBIGUOUS', 'physical_state_uncertain' => true]);

        $response = $this->withHeaders($this->headers())->getJson('/api/v1/recovery/items');

        $response->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.items.0.operationType', 'RECHARGE')
            ->assertJsonPath('data.items.0.status', 'AMBIGUOUS');
    }

    public function test_recovery_get_item(): void
    {
        $this->registerCard();

        $create = $this->withHeaders($this->headers())->postJson('/api/v1/wallet/cards/DEADFBEB/recharges', [
            'amountMinorUnits' => 5000,
            'idempotencyKey' => 'recovery-get-item-test',
            'terminalNumberHex' => '393230303031',
        ]);
        $operationId = $create->json('data.operation.id');

        $response = $this->withHeaders($this->headers())->getJson("/api/v1/recovery/items/RECHARGE/{$operationId}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.item.operationId', $operationId)
            ->assertJsonPath('data.item.operationType', 'RECHARGE');
    }
}
