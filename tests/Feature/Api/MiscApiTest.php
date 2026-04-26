<?php

namespace Tests\Feature\Api;

use App\Models\Route;
use App\Models\Stop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class MiscApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test searching for stops.
     *
     * @return void
     */
    public function test_can_search_stops()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Stop::create(['name' => 'Kalanki', 'latitude' => 27.6939, 'longitude' => 85.2817]);
        Stop::create(['name' => 'Koteshwor', 'latitude' => 27.6756, 'longitude' => 85.3460]);

        $response = $this->getJson('/api/misc/stops?search=Kala');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'content')
            ->assertJsonFragment(['name' => 'Kalanki']);
    }

    /**
     * Test route finder with a direct route.
     *
     * @return void
     */
    public function test_route_finder_direct_path()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $stop1 = Stop::create(['name' => 'Stop A', 'latitude' => 27.7000, 'longitude' => 85.3000]);
        $stop2 = Stop::create(['name' => 'Stop B', 'latitude' => 27.7100, 'longitude' => 85.3100]);
        $stop3 = Stop::create(['name' => 'Stop C', 'latitude' => 27.7200, 'longitude' => 85.3200]);

        $route = Route::create(['name' => 'Route 1', 'direction' => 'inbound', 'status' => 'active']);
        
        // Attach stops to route
        $route->stops()->createMany([
            ['stop_id' => $stop1->id, 'stop_name' => $stop1->name, 'latitude' => $stop1->latitude, 'longitude' => $stop1->longitude, 'order' => 1],
            ['stop_id' => $stop2->id, 'stop_name' => $stop2->name, 'latitude' => $stop2->latitude, 'longitude' => $stop2->longitude, 'order' => 2],
            ['stop_id' => $stop3->id, 'stop_name' => $stop3->name, 'latitude' => $stop3->latitude, 'longitude' => $stop3->longitude, 'order' => 3],
        ]);

        $response = $this->getJson("/api/misc/route-finder?from_stop_id={$stop1->id}&to_stop_id={$stop3->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'content' => [['total_fare_pts', 'legs']]]);
        
        $this->assertNotEmpty($response->json('content'));
        $this->assertEquals('Route 1', $response->json('content.0.legs.0.route_name'));
    }

    /**
     * Test route finder validation.
     *
     * @return void
     */
    public function test_route_finder_validation()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Missing parameters
        $response = $this->getJson('/api/misc/route-finder');
        $response->assertStatus(422)
            ->assertJson([
                'status' => false,
            ])
            ->assertJsonStructure([
                'content' => ['from_stop_id', 'to_stop_id']
            ]);

        // Same stop
        $stop = Stop::create(['name' => 'Stop A', 'latitude' => 27.7, 'longitude' => 85.3]);
        $response = $this->getJson("/api/misc/route-finder?from_stop_id={$stop->id}&to_stop_id={$stop->id}");
        $response->assertStatus(400);
    }
}
