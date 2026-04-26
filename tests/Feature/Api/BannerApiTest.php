<?php

namespace Tests\Feature\Api;

use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class BannerApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test fetching active banners.
     *
     * @return void
     */
    public function test_can_fetch_active_banners()
    {
        // Create active and inactive banners
        Banner::create([
            'position' => 'home_top',
            'type' => 'single',
            'title' => 'Promo 1',
            'is_active' => true,
        ]);

        Banner::create([
            'position' => 'wallet_top',
            'type' => 'single',
            'title' => 'Promo 2',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/banners');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'content')
            ->assertJsonFragment(['position' => 'home_top']);
    }

    /**
     * Test filtering banners by position.
     *
     * @return void
     */
    public function test_can_filter_banners_by_position()
    {
        Banner::create([
            'position' => 'home_top',
            'type' => 'single',
            'is_active' => true,
        ]);

        Banner::create([
            'position' => 'home_middle',
            'type' => 'single',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/banners?position=home_top');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'content')
            ->assertJsonFragment(['position' => 'home_top'])
            ->assertJsonMissing(['position' => 'home_middle']);
    }

    /**
     * Test validation for banner position filter.
     *
     * @return void
     */
    public function test_banner_position_validation()
    {
        $response = $this->getJson('/api/banners?position=invalid_position');

        $response->assertStatus(422)
            ->assertJson([
                'status' => false,
            ])
            ->assertJsonStructure([
                'content' => ['position']
            ]);
    }
}
