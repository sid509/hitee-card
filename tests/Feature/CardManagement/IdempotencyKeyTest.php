<?php

namespace Tests\Feature\CardManagement;

use App\Models\BlocklistEntry;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class IdempotencyKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_idempotency_key_replays_cached_response(): void
    {
        $headers = [
            'x-workstation-id' => 'ws-test',
            'Idempotency-Key' => 'idem-test-001',
        ];

        // First request — registers a card
        $first = $this->withHeaders($headers)->postJson('/api/v1/cards/register', [
            'uid' => 'DEADFBEB',
            'cardNumber' => '9999000000000002',
            'environment' => 'LAB',
        ]);

        $first->assertCreated();

        // Second request with same Idempotency-Key — should replay
        $second = $this->withHeaders($headers)->postJson('/api/v1/cards/register', [
            'uid' => 'DEADFBEB',
            'cardNumber' => '9999000000000002',
            'environment' => 'LAB',
        ]);

        $second->assertCreated()
            ->assertHeader('X-Idempotent-Replay', 'true');
    }

    public function test_no_idempotency_key_executes_normally(): void
    {
        $headers = ['x-workstation-id' => 'ws-test'];

        $response = $this->withHeaders($headers)->postJson('/api/v1/cards/check', [
            'uid' => 'AABBCCDD',
        ]);

        $response->assertOk()
            ->assertHeaderMissing('X-Idempotent-Replay');
    }
}
