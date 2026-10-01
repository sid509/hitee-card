<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fare Rules Engine API (Phase 15).
 *
 * CRUD for fare rules that validators can sync (Phase 35).
 * Supports distance-based, flat, and zoned fare types.
 * Versioned for audit trail.
 */
class FareRulesController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('fare_rules')->where('is_active', true);

        if ($routeId = $request->input('routeId')) {
            $query->where(function ($q) use ($routeId) {
                $q->where('route_id', $routeId)->orWhereNull('route_id');
            });
        }

        $rules = $query->orderBy('effective_from', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'fareRules' => $rules->map(fn ($r) => $this->toPublic($r))->toArray(),
            ],
        ]);
    }

    public function show(string $id)
    {
        $rule = DB::table('fare_rules')->where('id', $id)->first();
        if (!$rule) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Fare rule not found.'],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => ['fareRule' => $this->toPublic($rule)],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ruleCode' => 'required|string|max:32',
            'description' => 'nullable|string|max:500',
            'routeId' => 'nullable|string|max:64',
            'fareType' => 'required|string|in:DISTANCE,FLAT,ZONED',
            'baseFareMinorUnits' => 'nullable|integer|min:0',
            'ratePerKmMinorUnits' => 'nullable|integer|min:0',
            'minFareMinorUnits' => 'nullable|integer|min:0',
            'maxFareMinorUnits' => 'nullable|integer|min:0',
            'maxDistanceKm' => 'nullable|numeric|min:0',
            'flatFareMinorUnits' => 'nullable|integer|min:0',
            'effectiveFrom' => 'required|date',
            'effectiveTo' => 'nullable|date|after:effectiveFrom',
        ]);

        $id = Str::uuid()->toString();

        DB::table('fare_rules')->insert([
            'id' => $id,
            'rule_code' => $validated['ruleCode'],
            'description' => $validated['description'] ?? null,
            'route_id' => $validated['routeId'] ?? null,
            'fare_type' => $validated['fareType'],
            'base_fare_minor_units' => $validated['baseFareMinorUnits'] ?? 0,
            'rate_per_km_minor_units' => $validated['ratePerKmMinorUnits'] ?? 0,
            'min_fare_minor_units' => $validated['minFareMinorUnits'] ?? 0,
            'max_fare_minor_units' => $validated['maxFareMinorUnits'] ?? 0,
            'max_distance_km' => $validated['maxDistanceKm'] ?? 80.0,
            'flat_fare_minor_units' => $validated['flatFareMinorUnits'] ?? null,
            'effective_from' => $validated['effectiveFrom'],
            'effective_to' => $validated['effectiveTo'] ?? null,
            'is_active' => true,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rule = DB::table('fare_rules')->where('id', $id)->first();

        // Record initial version
        $this->recordVersion($rule, 'system', 'Initial creation');

        return response()->json([
            'success' => true,
            'data' => ['fareRule' => $this->toPublic($rule)],
        ], 201);
    }

    public function update(Request $request, string $id)
    {
        $rule = DB::table('fare_rules')->where('id', $id)->first();
        if (!$rule) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Fare rule not found.'],
            ], 404);
        }

        $validated = $request->validate([
            'description' => 'nullable|string|max:500',
            'routeId' => 'nullable|string|max:64',
            'fareType' => 'nullable|string|in:DISTANCE,FLAT,ZONED',
            'baseFareMinorUnits' => 'nullable|integer|min:0',
            'ratePerKmMinorUnits' => 'nullable|integer|min:0',
            'minFareMinorUnits' => 'nullable|integer|min:0',
            'maxFareMinorUnits' => 'nullable|integer|min:0',
            'maxDistanceKm' => 'nullable|numeric|min:0',
            'flatFareMinorUnits' => 'nullable|integer|min:0',
            'effectiveFrom' => 'nullable|date',
            'effectiveTo' => 'nullable|date|after:effectiveFrom',
            'isActive' => 'nullable|boolean',
        ]);

        $updates = array_filter([
            'description' => $validated['description'] ?? null,
            'route_id' => $validated['routeId'] ?? null,
            'fare_type' => $validated['fareType'] ?? null,
            'base_fare_minor_units' => $validated['baseFareMinorUnits'] ?? null,
            'rate_per_km_minor_units' => $validated['ratePerKmMinorUnits'] ?? null,
            'min_fare_minor_units' => $validated['minFareMinorUnits'] ?? null,
            'max_fare_minor_units' => $validated['maxFareMinorUnits'] ?? null,
            'max_distance_km' => $validated['maxDistanceKm'] ?? null,
            'flat_fare_minor_units' => $validated['flatFareMinorUnits'] ?? null,
            'effective_from' => $validated['effectiveFrom'] ?? null,
            'effective_to' => $validated['effectiveTo'] ?? null,
            'is_active' => isset($validated['isActive']) ? (bool) $validated['isActive'] : null,
        ], fn ($v) => $v !== null);

        $updates['version'] = $rule->version + 1;
        $updates['updated_at'] = now();

        DB::table('fare_rules')->where('id', $id)->update($updates);

        $updated = DB::table('fare_rules')->where('id', $id)->first();
        $this->recordVersion($updated, $request->user()->name ?? 'admin', 'Updated');

        return response()->json([
            'success' => true,
            'data' => ['fareRule' => $this->toPublic($updated)],
        ]);
    }

    public function destroy(string $id)
    {
        $rule = DB::table('fare_rules')->where('id', $id)->first();
        if (!$rule) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Fare rule not found.'],
            ], 404);
        }

        // Soft delete — just deactivate
        DB::table('fare_rules')->where('id', $id)->update([
            'is_active' => false,
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Phase 35: Fare rules sync endpoint for validators.
     * Returns the active fare rules for a given route (or all routes).
     */
    public function sync(Request $request)
    {
        $validatorVersion = (int) $request->input('version', 0);
        $validatorHash = $request->input('rulesetHash');

        $query = DB::table('fare_rules')
            ->where('is_active', true)
            ->where('effective_from', '<=', now()->toDateString())
            ->where(function ($q) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', now()->toDateString());
            });

        if ($routeId = $request->input('routeId')) {
            $query->where(function ($q) use ($routeId) {
                $q->where('route_id', $routeId)->orWhereNull('route_id');
            });
        }

        $rules = $query->orderBy('version', 'desc')->get();

        $maxVersion = (int) ($rules->max('version') ?? 0);

        // Fingerprint over the whole active rule set. max(version) alone is
        // not enough: deactivations and effective-window transitions remove a
        // rule without bumping any version, so a version-only validator would
        // keep stale rules forever. Validators that pass rulesetHash get an
        // exact comparison; legacy int-only validators always receive the
        // current set (tiny payload, always correct).
        $rulesetHash = substr(hash('sha256', $rules
            ->map(fn ($r) => "{$r->id}:{$r->version}:{$r->updated_at}")
            ->implode('|')), 0, 16);

        $upToDate = $validatorHash !== null
            && $validatorVersion >= $maxVersion
            && $validatorHash === $rulesetHash;

        return response()->json([
            'success' => true,
            'data' => [
                'upToDate' => $upToDate,
                'version' => $maxVersion,
                'rulesetHash' => $rulesetHash,
                'fareRules' => $upToDate
                    ? []
                    : $rules->map(fn ($r) => $this->toPublic($r))->toArray(),
            ],
        ]);
    }

    private function toPublic(object $r): array
    {
        return [
            'id' => $r->id,
            'ruleCode' => $r->rule_code,
            'description' => $r->description,
            'routeId' => $r->route_id,
            'fareType' => $r->fare_type,
            'baseFareMinorUnits' => $r->base_fare_minor_units,
            'ratePerKmMinorUnits' => $r->rate_per_km_minor_units,
            'minFareMinorUnits' => $r->min_fare_minor_units,
            'maxFareMinorUnits' => $r->max_fare_minor_units,
            'maxDistanceKm' => $r->max_distance_km,
            'flatFareMinorUnits' => $r->flat_fare_minor_units,
            'effectiveFrom' => $r->effective_from,
            'effectiveTo' => $r->effective_to,
            'isActive' => (bool) $r->is_active,
            'version' => $r->version,
        ];
    }

    private function recordVersion(object $rule, string $changedBy, string $reason): void
    {
        DB::table('fare_rule_versions')->insert([
            'id' => Str::uuid()->toString(),
            'fare_rule_id' => $rule->id,
            'version' => $rule->version,
            'snapshot' => json_encode($this->toPublic($rule)),
            'changed_by' => $changedBy,
            'change_reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
