<?php

namespace Tests\Feature\Api\Merchant;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MerchantProfileApiTest extends TestCase
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

    public function test_merchant_can_show_profile()
    {
        Sanctum::actingAs($this->merchant);
        $response = $this->getJson('/api/merchant/profile');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Test Merchant', 'email' => 'merchant@example.com']);
    }

    public function test_merchant_can_update_profile()
    {
        Sanctum::actingAs($this->merchant);
        $response = $this->putJson('/api/merchant/profile', [
            'name' => 'Updated Merchant',
            'email' => 'updated@example.com',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Updated Merchant', $this->merchant->fresh()->name);
    }

    public function test_merchant_can_update_password()
    {
        Sanctum::actingAs($this->merchant);
        $response = $this->postJson('/api/merchant/profile/password', [
            'current_password' => 'password',
            'new_password' => 'newpassword',
            'new_password_confirmation' => 'newpassword',
        ]);

        $response->assertStatus(200);
        $this->assertTrue(Hash::check('newpassword', $this->merchant->fresh()->password));
    }

    public function test_merchant_can_update_language()
    {
        Sanctum::actingAs($this->merchant);
        $response = $this->postJson('/api/merchant/profile/language', [
            'language' => 'ne',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('ne', $this->merchant->fresh()->preferred_language);
    }

    public function test_merchant_can_update_notification()
    {
        Sanctum::actingAs($this->merchant);
        $response = $this->postJson('/api/merchant/profile/notifications', [
            'enabled' => true,
        ]);

        $response->assertStatus(200);
        $this->assertTrue($this->merchant->fresh()->notification_enabled);
    }

    public function test_merchant_can_update_profile_image()
    {
        Storage::fake('public');
        Sanctum::actingAs($this->merchant);

        $file = UploadedFile::fake()->image('profile.jpg');

        $response = $this->postJson('/api/merchant/profile/image', [
            'image' => $file,
        ]);

        $response->assertStatus(200);
        $this->merchant->refresh();
        $this->assertNotNull($this->merchant->avatar_url);
        $this->assertStringContainsString('profile.jpg', $this->merchant->getFirstMediaUrl('avatar'));
    }
}
