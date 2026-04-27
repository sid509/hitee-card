<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\BalanceIn;
use App\Models\BalanceOut;
use App\Models\Card;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test fetching wallet summary.
     */
    public function test_user_can_fetch_wallet_summary()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['access']);

        $card = Card::create([
            'user_id' => $user->id,
            'card_number' => '1234567890',
            'hwid' => 'HWID123',
            'status' => 'active',
            'is_currently_active' => true,
        ]);

        BalanceIn::create([
            'user_id' => $user->id,
            'amount' => 500,
            'type' => 'manual',
            'status' => 'completed',
            'gateway_name' => 'Admin',
            'transaction_id' => 'TX1',
        ]);

        BalanceOut::create([
            'user_id' => $user->id,
            'amount' => 50,
            'type' => 'parking',
            'remarks' => 'Parking Fee',
        ]);

        $response = $this->getJson('/api/wallet');

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Wallet details fetched successfully',
            ])
            ->assertJsonFragment([
                'current_balance_pts' => 450.0,
            ]);
        
        $this->assertCount(1, $response->json('content.active_cards'));
        $this->assertCount(2, $response->json('content.latest_transactions'));
    }

    /**
     * Test fetching categories.
     */
    public function test_user_can_fetch_wallet_categories()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['access']);

        $response = $this->getJson('/api/wallet/categories');

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Categories fetched successfully',
            ])
            ->assertJsonFragment(['id' => 'all', 'name' => 'All'])
            ->assertJsonFragment(['id' => 'topup', 'name' => 'Direct Top-up']);
    }

    /**
     * Test fetching transactions with filters.
     */
    public function test_user_can_fetch_transactions_with_filters()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['access']);

        BalanceIn::create([
            'user_id' => $user->id,
            'amount' => 100,
            'type' => 'manual',
            'status' => 'completed',
        ]);

        BalanceOut::create([
            'user_id' => $user->id,
            'amount' => 20,
            'type' => 'parking',
        ]);

        // Test filter by topup
        $response = $this->getJson('/api/wallet/transactions?category=topup');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('content'));
        $this->assertEquals('credit', $response->json('content.0.direction'));

        // Test filter by parking
        $response = $this->getJson('/api/wallet/transactions?category=parking');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('content'));
        $this->assertEquals('debit', $response->json('content.0.direction'));

        // Test filter by all
        $response = $this->getJson('/api/wallet/transactions?category=all');
        $response->assertStatus(200);
        $this->assertCount(2, $response->json('content'));
    }

    /**
     * Test transaction pagination.
     */
    public function test_transaction_pagination()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['access']);

        for ($i = 0; $i < 15; $i++) {
            BalanceIn::create([
                'user_id' => $user->id,
                'amount' => 10,
                'type' => 'manual',
                'status' => 'completed',
            ]);
        }

        $response = $this->getJson('/api/wallet/transactions?perPage=10&page=1');
        $response->assertStatus(200);
        $this->assertCount(10, $response->json('content'));
        $this->assertEquals(15, $response->json('paginate.total'));

        $response = $this->getJson('/api/wallet/transactions?perPage=10&page=2');
        $response->assertStatus(200);
        $this->assertCount(5, $response->json('content'));
    }

    /**
     * Test topping up wallet.
     */
    public function test_user_can_topup_wallet()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['access']);

        $payload = [
            'amount' => 100,
            'type' => 'manual',
            'remarks' => 'Test Topup',
        ];

        $response = $this->postJson('/api/wallet/topup', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Wallet topped up successfully',
            ])
            ->assertJsonFragment([
                'amount_pts' => 100.0,
                'current_balance' => 100.0,
            ]);

        $this->assertDatabaseHas('balance_ins', [
            'user_id' => $user->id,
            'amount' => 100,
            'type' => 'manual',
            'status' => 'completed',
        ]);
    }
}
