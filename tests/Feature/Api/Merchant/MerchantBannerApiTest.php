<?php

namespace Tests\Feature\Api\Merchant;

use App\Models\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantBannerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Banner::create([
            'position' => 'merchant_home_top',
            'type' => 'single',
            'title' => 'Merchant Announcement',
            'is_active' => true,
        ]);

        Banner::create([
            'position' => 'home_top', // Customer banner
            'type' => 'single',
            'title' => 'Customer Announcement',
            'is_active' => true,
        ]);
    }

    public function test_merchant_can_get_banners()
    {
        $response = $this->getJson('/api/merchant/banners');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'content' => [
                    '*' => ['position', 'type', 'items']
                ]
            ]);
        
        // Should only see merchant banners by default
        $this->assertEquals(1, count($response->json('content')));
        $this->assertEquals('merchant_home_top', $response->json('content.0.position'));
    }

    public function test_merchant_can_filter_banners_by_position()
    {
        $response = $this->getJson('/api/merchant/banners?position=merchant_home_top');

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'content' => [
                    ['position' => 'merchant_home_top']
                ]
            ]);
    }

    public function test_merchant_cannot_request_customer_banners_via_merchant_api()
    {
        $response = $this->getJson('/api/merchant/banners?position=home_top');

        $response->assertStatus(422); // Validation error: not in [merchant_home_top, merchant_home_middle]
    }
}
