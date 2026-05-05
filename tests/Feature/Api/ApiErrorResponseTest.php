<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiErrorResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_404_returns_structured_response()
    {
        $response = $this->getJson('/api/non-existent-route');

        $response->assertStatus(404)
            ->assertJson([
                'status' => false,
                'message' => 'Resource Not Found',
                'content' => '',
            ]);
    }

    public function test_api_method_not_allowed_returns_structured_response()
    {
        // /api/banners is GET, let's try POST
        $response = $this->postJson('/api/banners');

        $response->assertStatus(405)
            ->assertJson([
                'status' => false,
                'message' => 'Method Not Allowed',
                'content' => '',
            ]);
    }
}
