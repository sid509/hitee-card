<?php

namespace Tests\Feature\Api\Merchant;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MerchantAuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected $merchant;
    protected $role;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = Role::create(['name' => 'Merchant', 'slug' => 'merchant']);
        $this->merchant = User::create([
            'name' => 'Test Merchant',
            'email' => 'merchant@example.com',
            'phone_number' => '9841234567',
            'password' => Hash::make('password'),
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
        $this->merchant->roles()->attach($this->role);
    }

    public function test_merchant_can_login()
    {
        $response = $this->postJson('/api/merchant/login', [
            'phone_number' => '9841234567',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'content' => [
                    'access_token',
                    'user' => ['id', 'name', 'merchant_balance']
                ]
            ]);
    }

    public function test_non_merchant_cannot_login_to_merchant_app()
    {
        $user = User::create([
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'phone_number' => '9800000000',
            'password' => Hash::make('password'),
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
        
        $response = $this->postJson('/api/merchant/login', [
            'phone_number' => '9800000000',
            'password' => 'password',
        ]);

        $response->assertStatus(403);
    }

    public function test_merchant_can_logout()
    {
        Sanctum::actingAs($this->merchant);

        $response = $this->postJson('/api/merchant/logout');

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Logged out successfully',
            ]);

        $this->assertCount(0, $this->merchant->tokens);
    }
}
