<?php

namespace Tests\Feature\Api\Customer;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FareRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_fare_rule(): void
    {
        $response = $this->postJson('/api/v1/fare-rules', [
            'ruleCode' => 'URBAN-001',
            'description' => 'Urban distance fare',
            'fareType' => 'DISTANCE',
            'baseFareMinorUnits' => 2000,
            'ratePerKmMinorUnits' => 500,
            'minFareMinorUnits' => 2000,
            'maxFareMinorUnits' => 5000,
            'maxDistanceKm' => 80.0,
            'effectiveFrom' => '2026-01-01',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.fareRule.ruleCode', 'URBAN-001')
            ->assertJsonPath('data.fareRule.fareType', 'DISTANCE')
            ->assertJsonPath('data.fareRule.version', 1);
    }

    public function test_list_fare_rules(): void
    {
        $this->postJson('/api/v1/fare-rules', [
            'ruleCode' => 'FLAT-001',
            'fareType' => 'FLAT',
            'flatFareMinorUnits' => 3000,
            'effectiveFrom' => '2026-01-01',
        ]);

        $response = $this->getJson('/api/v1/fare-rules');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.fareRules.0.ruleCode', 'FLAT-001');
    }

    public function test_show_fare_rule(): void
    {
        $create = $this->postJson('/api/v1/fare-rules', [
            'ruleCode' => 'ZONE-001',
            'fareType' => 'ZONED',
            'effectiveFrom' => '2026-01-01',
        ]);
        $id = $create->json('data.fareRule.id');

        $response = $this->getJson("/api/v1/fare-rules/{$id}");

        $response->assertOk()
            ->assertJsonPath('data.fareRule.id', $id);
    }

    public function test_update_fare_rule_increments_version(): void
    {
        $create = $this->postJson('/api/v1/fare-rules', [
            'ruleCode' => 'URBAN-002',
            'fareType' => 'DISTANCE',
            'baseFareMinorUnits' => 2000,
            'effectiveFrom' => '2026-01-01',
        ]);
        $id = $create->json('data.fareRule.id');

        $response = $this->putJson("/api/v1/fare-rules/{$id}", [
            'baseFareMinorUnits' => 2500,
            'minFareMinorUnits' => 2500,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.fareRule.version', 2)
            ->assertJsonPath('data.fareRule.baseFareMinorUnits', 2500);
    }

    public function test_delete_fare_rule_deactivates(): void
    {
        $create = $this->postJson('/api/v1/fare-rules', [
            'ruleCode' => 'DEL-001',
            'fareType' => 'FLAT',
            'flatFareMinorUnits' => 1000,
            'effectiveFrom' => '2026-01-01',
        ]);
        $id = $create->json('data.fareRule.id');

        $response = $this->deleteJson("/api/v1/fare-rules/{$id}");

        $response->assertOk()->assertJsonPath('success', true);

        // Should not appear in active list
        $list = $this->getJson('/api/v1/fare-rules');
        $list->assertJsonPath('data.fareRules', []);
    }

    public function test_sync_returns_up_to_date_when_version_and_hash_match(): void
    {
        $this->postJson('/api/v1/fare-rules', [
            'ruleCode' => 'SYNC-001',
            'fareType' => 'DISTANCE',
            'effectiveFrom' => '2026-01-01',
        ]);

        // First sync returns the full set plus the rulesetHash fingerprint
        $first = $this->getJson('/api/v1/fare-rules/sync?version=0');
        $first->assertOk()->assertJsonPath('data.upToDate', false);
        $hash = $first->json('data.rulesetHash');
        $version = $first->json('data.version');
        $this->assertNotEmpty($hash);

        // Repeating the same fingerprint means the client is current
        $second = $this->getJson("/api/v1/fare-rules/sync?version={$version}&rulesetHash={$hash}");
        $second->assertOk()
            ->assertJsonPath('data.upToDate', true)
            ->assertJsonPath('data.fareRules', []);
    }

    public function test_sync_detects_deactivation_via_ruleset_hash(): void
    {
        $create = $this->postJson('/api/v1/fare-rules', [
            'ruleCode' => 'SYNC-DEACT',
            'fareType' => 'DISTANCE',
            'effectiveFrom' => '2026-01-01',
        ]);
        $ruleId = $create->json('data.fareRule.id');

        $first = $this->getJson('/api/v1/fare-rules/sync?version=0');
        $hash = $first->json('data.rulesetHash');
        $version = $first->json('data.version');

        // Deactivate — bumps no version, so only the rulesetHash can tell
        $this->deleteJson("/api/v1/fare-rules/{$ruleId}")->assertOk();

        $second = $this->getJson("/api/v1/fare-rules/sync?version={$version}&rulesetHash={$hash}");
        $second->assertOk()
            ->assertJsonPath('data.upToDate', false);
    }

    public function test_sync_returns_rules_when_version_stale(): void
    {
        $this->postJson('/api/v1/fare-rules', [
            'ruleCode' => 'SYNC-002',
            'fareType' => 'DISTANCE',
            'effectiveFrom' => '2026-01-01',
        ]);

        $response = $this->getJson('/api/v1/fare-rules/sync?version=0');

        $response->assertOk()
            ->assertJsonPath('data.upToDate', false)
            ->assertJsonPath('data.fareRules.0.ruleCode', 'SYNC-002');
    }

    public function test_sync_filters_by_route_id(): void
    {
        $this->postJson('/api/v1/fare-rules', [
            'ruleCode' => 'ROUTE-12-FARE',
            'fareType' => 'DISTANCE',
            'routeId' => 'ROUTE-12',
            'effectiveFrom' => '2026-01-01',
        ]);

        $this->postJson('/api/v1/fare-rules', [
            'ruleCode' => 'ROUTE-99-FARE',
            'fareType' => 'DISTANCE',
            'routeId' => 'ROUTE-99',
            'effectiveFrom' => '2026-01-01',
        ]);

        $response = $this->getJson('/api/v1/fare-rules/sync?routeId=ROUTE-12&version=0');

        $response->assertOk()
            ->assertJsonPath('data.fareRules.0.ruleCode', 'ROUTE-12-FARE');
    }

    public function test_show_not_found(): void
    {
        $response = $this->getJson('/api/v1/fare-rules/' . Str::uuid()->toString());

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }
}
