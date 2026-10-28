<?php

namespace Tests\Feature\Api;

use App\Models\Bus;
use App\Models\Parking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Road-distance (OSRM) behaviour for the customer list/nearby endpoints.
 *
 * phpunit.xml disables routing (OSRM_BASE_URL=""), so each test opts in via
 * enableRouting() and fakes the OSRM table response.
 */
class RoadDistanceApiTest extends TestCase
{
    use RefreshDatabase;

    // ~0.13km straight-line between these two points (Kalanki area)
    private const ORIGIN_LAT = 27.6939;
    private const ORIGIN_LNG = 85.2817;
    private const DEST_LAT   = 27.6940;
    private const DEST_LNG   = 85.2818;

    private function enableRouting(array $distancesMeters, array $durationsSeconds = []): void
    {
        config(['services.osrm.base_url' => 'https://osrm.test']);
        config(['services.osrm.cache_ttl' => 0]);

        Http::preventStrayRequests();
        Http::fake([
            'osrm.test/*' => Http::response([
                'code'      => 'Ok',
                'distances' => [$distancesMeters],
                'durations' => [$durationsSeconds],
            ]),
        ]);
    }

    public function test_parkings_report_road_distance_and_filter_by_it(): void
    {
        Parking::create([
            'name' => 'Kalanki Parking',
            'location' => 'Kalanki Chowk',
            'total_capacity' => 100,
            'status' => 'opened',
            'latitude' => self::DEST_LAT,
            'longitude' => self::DEST_LNG,
        ]);

        // Road distance 0.9km / 6min even though straight-line is ~0.13km
        $this->enableRouting([900], [360]);

        // Excluded by road radius even though it sits inside the straight-line radius
        $response = $this->getJson(
            '/api/parkings?latitude=' . self::ORIGIN_LAT . '&longitude=' . self::ORIGIN_LNG . '&radius=0.5'
        );
        $response->assertStatus(200);
        $this->assertCount(0, $response->json('content'));

        $response = $this->getJson(
            '/api/parkings?latitude=' . self::ORIGIN_LAT . '&longitude=' . self::ORIGIN_LNG . '&radius=1'
        );
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('content'));
        $this->assertEquals(0.9, $response->json('content.0.distance_km'));
        $this->assertEquals(6.0, $response->json('content.0.duration_min'));
        $this->assertEquals('road', $response->json('content.0.distance_source'));
    }

    public function test_parkings_fall_back_to_straight_line_when_routing_unavailable(): void
    {
        Parking::create([
            'name' => 'Kalanki Parking',
            'location' => 'Kalanki Chowk',
            'total_capacity' => 100,
            'status' => 'opened',
            'latitude' => self::DEST_LAT,
            'longitude' => self::DEST_LNG,
        ]);

        config(['services.osrm.base_url' => 'https://osrm.test']);
        config(['services.osrm.cache_ttl' => 0]);
        Http::fake(['*' => Http::response('service unavailable', 503)]);

        $response = $this->getJson(
            '/api/parkings?latitude=' . self::ORIGIN_LAT . '&longitude=' . self::ORIGIN_LNG . '&radius=1'
        );
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('content'));
        $this->assertEquals('straight_line', $response->json('content.0.distance_source'));
        $this->assertLessThan(0.2, $response->json('content.0.distance_km'));
        $this->assertNull($response->json('content.0.duration_min'));
    }

    public function test_buses_report_road_distance(): void
    {
        Bus::create([
            'name' => 'Kalanki Express',
            'bus_number' => 'BA 1 PA 1234',
            'total_capacity' => 40,
            'hwid' => 'BUS-RD-01',
            'status' => 'active',
            'latitude' => self::DEST_LAT,
            'longitude' => self::DEST_LNG,
        ]);

        $this->enableRouting([2000], [600]);

        $response = $this->getJson(
            '/api/buses?latitude=' . self::ORIGIN_LAT . '&longitude=' . self::ORIGIN_LNG . '&radius=1'
        );
        $response->assertStatus(200);
        $this->assertCount(0, $response->json('content'));

        $response = $this->getJson(
            '/api/buses?latitude=' . self::ORIGIN_LAT . '&longitude=' . self::ORIGIN_LNG . '&radius=3'
        );
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('content'));
        $this->assertEquals(2.0, $response->json('content.0.distance_km'));
        $this->assertEquals('road', $response->json('content.0.distance_source'));
    }

    public function test_nearby_uses_road_distance_across_types(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Bus::create([
            'name' => 'Kalanki Express',
            'bus_number' => 'BA 1 PA 1234',
            'total_capacity' => 40,
            'hwid' => 'BUS-RD-02',
            'status' => 'active',
            'latitude' => self::DEST_LAT,
            'longitude' => self::DEST_LNG,
        ]);
        Parking::create([
            'name' => 'Kalanki Parking',
            'location' => 'Kalanki Chowk',
            'total_capacity' => 100,
            'status' => 'opened',
            'latitude' => self::DEST_LAT,
            'longitude' => self::DEST_LNG,
        ]);

        // sets are ordered buses → parkings
        $this->enableRouting([900, 800], [360, 480]);

        $response = $this->getJson(
            '/api/misc/nearby?lat=' . self::ORIGIN_LAT . '&long=' . self::ORIGIN_LNG . '&radius=1'
        );
        $response->assertStatus(200);
        $this->assertEquals(0.9, $response->json('content.buses.0.distance_km'));
        $this->assertEquals('road', $response->json('content.buses.0.distance_source'));
        $this->assertEquals(0.8, $response->json('content.parkings.0.distance_km'));

        $response = $this->getJson(
            '/api/misc/nearby?lat=' . self::ORIGIN_LAT . '&long=' . self::ORIGIN_LNG . '&radius=0.5'
        );
        $response->assertStatus(200);
        $this->assertCount(0, $response->json('content.buses'));
        $this->assertCount(0, $response->json('content.parkings'));
    }
}
