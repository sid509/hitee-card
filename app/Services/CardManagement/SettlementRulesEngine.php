<?php

namespace App\Services\CardManagement;

/**
 * JSON Settlement Rules Engine (Target §5.2).
 *
 * Replaces the hardcoded 10% commission with a configurable JSON rules engine.
 * BDM/Ops configure settlement commission structures via JSON logic, e.g.:
 *
 * {
 *   "rules": [
 *     {
 *       "condition": { "operator": "CITY-OPS", "routeType": "URBAN" },
 *       "commissionPercent": 2,
 *       "description": "City takes 2% on urban routes"
 *     },
 *     {
 *       "condition": { "operator": "CITY-OPS", "routeType": "EXPRESS" },
 *       "commissionPercent": 5,
 *       "description": "City takes 5% on express routes"
 *     },
 *     {
 *       "condition": { "default": true },
 *       "commissionPercent": 10,
 *       "description": "Default 10% commission"
 *     }
 *   ]
 * }
 *
 * The first matching rule wins. If no rule matches, the default rate is used.
 * Rules are stored in cm_settlement_rules and versioned.
 */
class SettlementRulesEngine
{
    /**
     * Evaluate the commission for a settlement entry based on the rules.
     *
     * @param array $entry The settlement entry context (deviceId, operatorId, routeId, fareMinorUnits, etc.)
     * @param array $rulesConfig The JSON rules configuration
     * @return SettlementRuleResult The commission and payout amounts
     */
    public function evaluate(array $entry, array $rulesConfig): SettlementRuleResult
    {
        $rules = $rulesConfig['rules'] ?? [];
        $fare = $entry['fareMinorUnits'] ?? 0;

        foreach ($rules as $rule) {
            if ($this->matches($entry, $rule['condition'] ?? [])) {
                $percent = (float) ($rule['commissionPercent'] ?? 0);
                $fixed = (int) ($rule['commissionFixedMinorUnits'] ?? 0);
                $commission = (int) round($fare * $percent / 100) + $fixed;
                $commission = min($commission, $fare); // Never exceed fare
                $payout = $fare - $commission;

                return new SettlementRuleResult(
                    commissionMinorUnits: $commission,
                    payoutMinorUnits: $payout,
                    commissionPercent: $percent,
                    matchedRuleDescription: $rule['description'] ?? null,
                    matchedRuleIndex: array_search($rule, $rules, true),
                );
            }
        }

        // No rule matched — use default (0% commission, full payout)
        return new SettlementRuleResult(
            commissionMinorUnits: 0,
            payoutMinorUnits: $fare,
            commissionPercent: 0,
            matchedRuleDescription: 'No rule matched — zero commission',
            matchedRuleIndex: null,
        );
    }

    /**
     * Check if an entry matches a rule's condition.
     * A condition is a set of field=value pairs; all must match.
     * Special key "default" always matches.
     */
    private function matches(array $entry, array $condition): bool
    {
        if (isset($condition['default']) && $condition['default'] === true) {
            return true;
        }

        foreach ($condition as $field => $expected) {
            if ($field === 'default') continue;

            $actual = $this->getField($entry, $field);
            if ($actual !== $expected) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get a field from the entry, supporting nested keys.
     */
    private function getField(array $entry, string $field): mixed
    {
        // Map common field names
        $fieldMap = [
            'operator' => 'operatorId',
            'route' => 'routeId',
            'routeType' => 'routeType',
            'device' => 'deviceId',
            'tripCount' => 'tripCount',
        ];

        $key = $fieldMap[$field] ?? $field;
        return $entry[$key] ??
            $entry['metadata'][$field] ??
            null;
    }

    /**
     * Validate a rules configuration.
     * @return array List of validation errors (empty if valid)
     */
    public static function validate(array $config): array
    {
        $errors = [];

        if (!isset($config['rules']) || !is_array($config['rules'])) {
            $errors[] = 'Configuration must contain a "rules" array.';
            return $errors;
        }

        if (empty($config['rules'])) {
            $errors[] = 'Rules array cannot be empty.';
            return $errors;
        }

        $hasDefault = false;
        foreach ($config['rules'] as $i => $rule) {
            $prefix = "Rule $i: ";

            if (!isset($rule['condition']) || !is_array($rule['condition'])) {
                $errors[] = $prefix . 'Must contain a "condition" object.';
                continue;
            }

            if (isset($rule['condition']['default']) && $rule['condition']['default'] === true) {
                $hasDefault = true;
            }

            if (!isset($rule['commissionPercent']) && !isset($rule['commissionFixedMinorUnits'])) {
                $errors[] = $prefix . 'Must specify either commissionPercent or commissionFixedMinorUnits.';
            }

            if (isset($rule['commissionPercent'])) {
                $pct = $rule['commissionPercent'];
                if (!is_numeric($pct) || $pct < 0 || $pct > 100) {
                    $errors[] = $prefix . 'commissionPercent must be between 0 and 100.';
                }
            }
        }

        if (!$hasDefault) {
            $errors[] = 'At least one rule must have "default": true as a fallback.';
        }

        return $errors;
    }
}

/**
 * Result of a settlement rule evaluation.
 */
class SettlementRuleResult
{
    public function __construct(
        public readonly int $commissionMinorUnits,
        public readonly int $payoutMinorUnits,
        public readonly float $commissionPercent,
        public readonly ?string $matchedRuleDescription,
        public readonly mixed $matchedRuleIndex,
    ) {}
}
