<?php

namespace Tests\Feature\Api;

use App\Models\Setting;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class SupportApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test fetching support contact information.
     *
     * @return void
     */
    public function test_can_fetch_support_info()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Seed settings
        Setting::updateOrCreate(['key' => 'support_email'], ['value' => 'test@hitee.ai']);
        Setting::updateOrCreate(['key' => 'support_phone'], ['value' => '9800000000']);

        $response = $this->getJson('/api/support');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'support_email' => 'test@hitee.ai',
                'support_phone' => '9800000000',
            ]);
    }

    /**
     * Test submitting a support request successfully.
     *
     * @return void
     */
    public function test_can_submit_support_request()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'subject' => 'Issue with payment',
            'message' => 'I tried to top up but it failed after deducting money.',
        ];

        $response = $this->postJson('/api/support', $payload);

        $response->assertStatus(200)
            ->assertJson(['status' => true]);

        $this->assertDatabaseHas('support_requests', [
            'user_id' => $user->id,
            'subject' => 'Issue with payment',
            'message' => 'I tried to top up but it failed after deducting money.',
        ]);
    }

    /**
     * Test support request validation.
     *
     * @return void
     */
    public function test_support_request_validation()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Short message failure
        $response = $this->postJson('/api/support', [
            'subject' => 'Hi',
            'message' => 'Help',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => false,
            ])
            ->assertJsonStructure([
                'content' => ['subject', 'message']
            ]);

        // Missing message failure
        $response = $this->postJson('/api/support', [
            'subject' => 'Valid Subject Line',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => false,
            ])
            ->assertJsonStructure([
                'content' => ['message']
            ]);
    }

    /**
     * Test unauthorized access to support endpoints.
     *
     * @return void
     */
    public function test_unauthorized_access_to_support()
    {
        $response = $this->getJson('/api/support');
        $response->assertStatus(401);

        $response = $this->postJson('/api/support', ['message' => 'Test message']);
        $response->assertStatus(401);
    }
}
