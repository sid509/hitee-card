<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Ride;
use App\Models\Tap;
use App\Models\Card;
use App\Models\Bus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test fetching profile successfully.
     */
    public function test_user_can_fetch_profile()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['access']);

        $response = $this->getJson('/api/profile');

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Profile fetched successfully',
            ])
            ->assertJsonStructure([
                'content' => [
                    'id', 'name', 'email', 'phone_number', 'stats', 'settings'
                ]
            ]);
    }

    /**
     * Test profile fetch fails with refresh token.
     */
    public function test_profile_fetch_fails_with_refresh_token()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['refresh']);

        $response = $this->getJson('/api/profile');

        $response->assertStatus(403)
            ->assertJson([
                'status' => false,
                'message' => 'Invalid token type for this action',
            ]);
    }

    /**
     * Test updating profile successfully.
     */
    public function test_user_can_update_profile()
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);
        Sanctum::actingAs($user, ['access']);

        $response = $this->putJson('/api/profile', [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Profile updated successfully',
                'content' => [
                    'name' => 'New Name',
                    'email' => 'new@example.com',
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);
    }

    /**
     * Test updating profile image.
     */
    public function test_user_can_update_profile_image()
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['access']);

        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->postJson('/api/profile/image', [
            'image' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Profile image updated successfully',
            ]);
    }

    /**
     * Test updating password successfully.
     */
    public function test_user_can_update_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);
        Sanctum::actingAs($user, ['access']);

        $response = $this->postJson('/api/profile/password', [
            'current_password' => 'old-password',
            'new_password' => 'new-password',
            'new_password_confirmation' => 'new-password',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Password updated successfully',
            ]);

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    /**
     * Test updating password failure with wrong current password.
     */
    public function test_update_password_fails_with_wrong_current_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);
        Sanctum::actingAs($user, ['access']);

        $response = $this->postJson('/api/profile/password', [
            'current_password' => 'wrong-password',
            'new_password' => 'new-password',
            'new_password_confirmation' => 'new-password',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'status' => false,
                'message' => 'Current password does not match',
            ]);
    }

    /**
     * Test fetching rides.
     */
    public function test_user_can_fetch_rides()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['access']);

        // Manual creation since no factory
        $card = Card::create([
            'user_id' => $user->id,
            'card_number' => '1234567890',
            'hwid' => 'HWID123',
            'status' => 'active',
        ]);

        $bus = Bus::create([
            'name' => 'KTM-01',
            'bus_number' => 'BA 1 PA 1234',
            'hwid' => 'BUS-HWID-01',
            'status' => 'active',
        ]);

        // Create a tap_in for the ride
        $tapIn = Tap::create([
            'user_id' => $user->id,
            'card_id' => $card->id,
            'type' => 'in',
            'latitude' => 27.7,
            'longitude' => 85.3,
            'resolved_location_name' => 'Station A',
            'reference_id' => $bus->id,
            'reference_type' => Bus::class,
        ]);

        Ride::create([
            'user_id' => $user->id,
            'card_id' => $card->id,
            'tap_in_id' => $tapIn->id,
            'fare_amount' => 20,
            'status' => 'completed',
            'reference_id' => $bus->id,
            'reference_type' => Bus::class,
        ]);

        $response = $this->getJson('/api/profile/rides');

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Rides fetched successfully',
            ]);
        
        $this->assertCount(1, $response->json('content'));
    }

    /**
     * Test fetching taps.
     */
    public function test_user_can_fetch_taps()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['access']);

        $card = Card::create([
            'user_id' => $user->id,
            'card_number' => '1234567890',
            'hwid' => 'HWID123',
            'status' => 'active',
        ]);

        $bus = Bus::create([
            'name' => 'KTM-01',
            'bus_number' => 'BA 1 PA 1234',
            'hwid' => 'BUS-HWID-01',
            'status' => 'active',
        ]);

        Tap::create([
            'user_id' => $user->id,
            'card_id' => $card->id,
            'type' => 'in',
            'latitude' => 27.7,
            'longitude' => 85.3,
            'resolved_location_name' => 'Station A',
            'reference_id' => $bus->id,
            'reference_type' => Bus::class,
        ]);

        $response = $this->getJson('/api/profile/taps');

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Tap logs fetched successfully',
            ]);
    }

    /**
     * Test updating language.
     */
    public function test_user_can_update_language()
    {
        $user = User::factory()->create(['preferred_language' => 'en']);
        Sanctum::actingAs($user, ['access']);

        $response = $this->postJson('/api/profile/language', [
            'language' => 'ne',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('ne', $user->fresh()->preferred_language);
    }

    /**
     * Test updating notification preference.
     */
    public function test_user_can_update_notification_preference()
    {
        $user = User::factory()->create(['notification_enabled' => true]);
        Sanctum::actingAs($user, ['access']);

        $response = $this->postJson('/api/profile/notifications', [
            'enabled' => false,
        ]);

        $response->assertStatus(200);
        $this->assertFalse((bool)$user->fresh()->notification_enabled);
    }
}
