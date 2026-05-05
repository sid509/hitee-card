<?php

namespace Tests\Feature\Api\Merchant;

use App\Models\Role;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MerchantSupportApiTest extends TestCase
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

    public function test_merchant_can_get_support_info()
    {
        Sanctum::actingAs($this->merchant);
        $response = $this->getJson('/api/merchant/support');

        $response->assertStatus(200)
            ->assertJsonFragment(['support_email' => 'support@hitee.ai']);
    }

    public function test_merchant_can_submit_support_request()
    {
        Sanctum::actingAs($this->merchant);
        $response = $this->postJson('/api/merchant/support', [
            'subject' => 'New Support Request',
            'message' => 'Need help with my bus. It is a long message for validation.',
        ]);

        $response->assertStatus(200); // Controller returns 200 default
        $this->assertDatabaseHas('support_requests', [
            'user_id' => $this->merchant->id,
            'subject' => 'New Support Request',
        ]);
    }
}
