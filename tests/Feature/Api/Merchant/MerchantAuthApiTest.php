<?php

namespace Tests\Feature\Api\Merchant;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Socialite\Facades\Socialite;
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

    public function test_merchant_can_login_via_biometric()
    {
        $token = $this->merchant->createToken('merchant_access_token', ['access'])->plainTextToken;

        $response = $this->postJson('/api/merchant/biometric-login', [
            'access_token' => $token,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'content' => [
                    'access_token',
                    'refresh_token',
                    'user' => ['id', 'name', 'merchant_balance']
                ]
            ]);
    }

    public function test_merchant_cannot_login_via_biometric_with_invalid_token()
    {
        $response = $this->postJson('/api/merchant/biometric-login', [
            'access_token' => 'invalid-token',
        ]);

        $response->assertStatus(403);
    }

    public function test_merchant_can_refresh_token()
    {
        $refreshToken = $this->merchant->createToken('merchant_refresh_token', ['refresh'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $refreshToken)
            ->postJson('/api/merchant/auth/refresh');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'content' => [
                    'access_token',
                    'refresh_token',
                    'token_type'
                ]
            ]);
    }

    public function test_merchant_cannot_refresh_token_with_access_token()
    {
        $accessToken = $this->merchant->createToken('merchant_access_token', ['access'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $accessToken)
            ->postJson('/api/merchant/auth/refresh');

        $response->assertStatus(403);
    }

    public function test_merchant_can_login_via_social()
    {
        $abstractUser = \Mockery::mock('Laravel\Socialite\Two\User');
        $abstractUser->shouldReceive('getEmail')->andReturn('merchant@example.com');
        $abstractUser->shouldReceive('getName')->andReturn('Test Merchant');

        $provider = \Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('userFromToken')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->postJson('/api/merchant/social', [
            'provider' => 'google',
            'access_token' => 'valid-token',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'content' => ['access_token', 'user']
            ]);
    }

    public function test_merchant_cannot_login_via_social_if_not_found()
    {
        $abstractUser = \Mockery::mock('Laravel\Socialite\Two\User');
        $abstractUser->shouldReceive('getEmail')->andReturn('nonexistent@example.com');

        $provider = \Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('userFromToken')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->postJson('/api/merchant/social', [
            'provider' => 'google',
            'access_token' => 'valid-token',
        ]);

        $response->assertStatus(404);
    }

    public function test_merchant_can_request_forgot_password_link()
    {
        Notification::fake();

        $response = $this->postJson('/api/merchant/forgot-password', [
            'email' => 'merchant@example.com',
        ]);

        $response->assertStatus(200);
    }

    public function test_merchant_can_reset_password()
    {
        $token = Password::createToken($this->merchant);

        $response = $this->postJson('/api/merchant/reset-password', [
            'token' => $token,
            'email' => 'merchant@example.com',
            'password' => 'new_password',
            'password_confirmation' => 'new_password',
        ]);

        $response->assertStatus(200);

        $this->assertTrue(Hash::check('new_password', $this->merchant->fresh()->password));
    }
}
