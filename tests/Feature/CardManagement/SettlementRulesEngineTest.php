<?php

namespace Tests\Feature\CardManagement;

use App\Services\CardManagement\SettlementRulesEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettlementRulesEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_rule_10_percent(): void
    {
        $engine = new SettlementRulesEngine();
        $result = $engine->evaluate(
            ['fareMinorUnits' => 10000, 'operatorId' => 'OPS-001'],
            [
                'rules' => [
                    ['condition' => ['default' => true], 'commissionPercent' => 10, 'description' => 'Default 10%'],
                ],
            ],
        );

        $this->assertEquals(1000, $result->commissionMinorUnits);
        $this->assertEquals(9000, $result->payoutMinorUnits);
        $this->assertEquals(10.0, $result->commissionPercent);
    }

    public function test_operator_specific_rule_overrides_default(): void
    {
        $engine = new SettlementRulesEngine();
        $result = $engine->evaluate(
            ['fareMinorUnits' => 10000, 'operatorId' => 'CITY-OPS'],
            [
                'rules' => [
                    ['condition' => ['operator' => 'CITY-OPS'], 'commissionPercent' => 2, 'description' => 'City 2%'],
                    ['condition' => ['default' => true], 'commissionPercent' => 10, 'description' => 'Default 10%'],
                ],
            ],
        );

        $this->assertEquals(200, $result->commissionMinorUnits);
        $this->assertEquals(9800, $result->payoutMinorUnits);
        $this->assertEquals('City 2%', $result->matchedRuleDescription);
    }

    public function test_fixed_commission_amount(): void
    {
        $engine = new SettlementRulesEngine();
        $result = $engine->evaluate(
            ['fareMinorUnits' => 5000, 'operatorId' => 'FIXED-OPS'],
            [
                'rules' => [
                    ['condition' => ['operator' => 'FIXED-OPS'], 'commissionFixedMinorUnits' => 100, 'description' => 'Fixed 1.00'],
                    ['condition' => ['default' => true], 'commissionPercent' => 10],
                ],
            ],
        );

        $this->assertEquals(100, $result->commissionMinorUnits);
        $this->assertEquals(4900, $result->payoutMinorUnits);
    }

    public function test_commission_never_exceeds_fare(): void
    {
        $engine = new SettlementRulesEngine();
        $result = $engine->evaluate(
            ['fareMinorUnits' => 100, 'operatorId' => 'GREEDY'],
            [
                'rules' => [
                    ['condition' => ['operator' => 'GREEDY'], 'commissionPercent' => 200, 'description' => '200%'],
                    ['condition' => ['default' => true], 'commissionPercent' => 10],
                ],
            ],
        );

        $this->assertEquals(100, $result->commissionMinorUnits);
        $this->assertEquals(0, $result->payoutMinorUnits);
    }

    public function test_no_rule_matches_zero_commission(): void
    {
        $engine = new SettlementRulesEngine();
        $result = $engine->evaluate(
            ['fareMinorUnits' => 5000, 'operatorId' => 'UNKNOWN'],
            [
                'rules' => [
                    ['condition' => ['operator' => 'KNOWN'], 'commissionPercent' => 5],
                ],
            ],
        );

        $this->assertEquals(0, $result->commissionMinorUnits);
        $this->assertEquals(5000, $result->payoutMinorUnits);
    }

    public function test_validate_valid_config(): void
    {
        $errors = SettlementRulesEngine::validate([
            'rules' => [
                ['condition' => ['operator' => 'CITY'], 'commissionPercent' => 2],
                ['condition' => ['default' => true], 'commissionPercent' => 10],
            ],
        ]);

        $this->assertEmpty($errors);
    }

    public function test_validate_missing_default(): void
    {
        $errors = SettlementRulesEngine::validate([
            'rules' => [
                ['condition' => ['operator' => 'CITY'], 'commissionPercent' => 2],
            ],
        ]);

        $this->assertContains('At least one rule must have "default": true as a fallback.', $errors);
    }

    public function test_validate_invalid_percent(): void
    {
        $errors = SettlementRulesEngine::validate([
            'rules' => [
                ['condition' => ['default' => true], 'commissionPercent' => 150],
            ],
        ]);

        $this->assertNotEmpty($errors);
    }

    public function test_validate_empty_rules(): void
    {
        $errors = SettlementRulesEngine::validate(['rules' => []]);
        $this->assertContains('Rules array cannot be empty.', $errors);
    }
}
