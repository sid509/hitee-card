<?php

namespace App\Http\Controllers\CardManagement;

use App\Models\BlocklistCursor;
use App\Models\BlocklistEntry;
use App\Models\ValidatorDevice;
use App\Models\ValidatorTrip;
use App\Services\CardManagement\CardManagementError;
use App\Services\CardManagement\ResponseEnvelope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Validator-facing API: device registration, trip sync, blocklist download.
 * Phases 7, 13-14, 44-46.
 */
final class ValidatorController extends BaseCardManagementController
{
    /**
     * POST /api/v1/devices/register
     * Phase 7: Register a validator device.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'deviceId' => 'required|string|max:128',
            'vehicleId' => 'nullable|string|max:128',
            'routeId' => 'nullable|string|max:128',
            'terminalNumber' => 'nullable|string|max:12',
            'firmwareVersion' => 'nullable|string|max:64',
            'metadata' => 'nullable|array',
            'provisioningSecret' => 'nullable|string|max:256',
            'rotateToken' => 'nullable|boolean',
        ]);

        logger('[Validator] Device register', [
            'device_id' => $validated['deviceId'],
            'vehicle_id' => $validated['vehicleId'] ?? null,
        ]);

        $device = ValidatorDevice::where('device_id', $validated['deviceId'])->first();

        if ($device) {
            // Re-registration of an existing device is authenticated: the
            // caller must present either the device's current Bearer token or
            // the operator-issued provisioning secret. Otherwise anyone could
            // claim a deviceId and rotate away its credentials.
            $bearer = $request->bearerToken();
            $secret = config('card_management.validator.provisioning_secret');
            $authorizedByToken = $bearer !== null
                && hash_equals($device->api_token, hash('sha256', $bearer));
            $authorizedBySecret = $secret !== null && $secret !== ''
                && isset($validated['provisioningSecret'])
                && hash_equals($secret, $validated['provisioningSecret']);

            if (!$authorizedByToken && !$authorizedBySecret) {
                throw new CardManagementError('DEVICE_UNAUTHORIZED', 401, 'Re-registration requires the current device token or provisioning secret.');
            }

            // A suspended or decommissioned device must not reactivate itself.
            if (in_array($device->status, ['SUSPENDED', 'DECOMMISSIONED'], true)) {
                throw new CardManagementError('DEVICE_NOT_ACTIVE', 409, "Device is {$device->status} and cannot re-register.");
            }

            $update = [
                'vehicle_id' => $validated['vehicleId'] ?? $device->vehicle_id,
                'route_id' => $validated['routeId'] ?? $device->route_id,
                'terminal_number_hex' => $validated['terminalNumber'] ?? $device->terminal_number_hex,
                'firmware_version' => $validated['firmwareVersion'] ?? $device->firmware_version,
                'metadata' => $validated['metadata'] ?? $device->metadata,
            ];

            // Token is preserved across re-registration unless an explicit
            // rotation is requested — a metadata update must not lock the
            // device out of the API.
            $rawToken = null;
            if ($request->boolean('rotateToken')) {
                $rawToken = Str::random(64);
                $update['api_token'] = hash('sha256', $rawToken);
            }

            $device->update($update);
        } else {
            // First registration: issue a fresh token (returned once, stored hashed).
            $rawToken = Str::random(64);
            $device = ValidatorDevice::create([
                'device_id' => $validated['deviceId'],
                'vehicle_id' => $validated['vehicleId'] ?? null,
                'route_id' => $validated['routeId'] ?? null,
                'terminal_number_hex' => $validated['terminalNumber'] ?? null,
                'firmware_version' => $validated['firmwareVersion'] ?? null,
                'metadata' => $validated['metadata'] ?? null,
                'status' => 'ACTIVE',
                'api_token' => hash('sha256', $rawToken),
            ]);
        }

        // Create blocklist cursor if not exists
        BlocklistCursor::firstOrCreate(['device_id' => $device->id]);

        return ResponseEnvelope::success([
            'device' => [
                'id' => $device->id,
                'deviceId' => $device->device_id,
                'vehicleId' => $device->vehicle_id,
                'routeId' => $device->route_id,
                'status' => $device->status,
                'apiToken' => $rawToken,  // non-null only when a token was issued/rotated
                'registeredAt' => $device->registered_at?->toIso8601ZuluString(),
            ],
        ], 201);
    }

    /**
     * POST /api/v1/devices/heartbeat
     * Phase 48: Device heartbeat monitoring.
     */
    public function heartbeat(Request $request)
    {
        $device = $this->authenticateDevice($request);

        logger('[Validator] Heartbeat', [
            'device_id' => $device->device_id,
        ]);

        $device->update(['last_heartbeat_at' => now()]);

        return ResponseEnvelope::success([
            'device' => [
                'id' => $device->id,
                'status' => $device->status,
                'lastHeartbeatAt' => $device->last_heartbeat_at?->toIso8601ZuluString(),
            ],
        ]);
    }

    /**
     * POST /api/v1/devices/{deviceId}/trips/sync
     * Phases 44-46: Sync trips from validator to platform.
     */
    public function syncTrips(Request $request, string $deviceId)
    {
        $device = $this->authenticateDevice($request, $deviceId);

        logger('[Validator] Trip sync request', [
            'device_id' => $device->device_id,
            'trip_count' => count($request->input('trips', [])),
        ]);

        $validated = $request->validate([
            'trips' => 'required|array|max:500',
            'trips.*.tripId' => 'required|string|max:128',
            'trips.*.cardUid' => 'required|string|max:32',
            'trips.*.cardType' => 'nullable|string|max:32',
            'trips.*.tapInAt' => 'nullable|date',
            'trips.*.tapInLat' => 'nullable|numeric',
            'trips.*.tapInLng' => 'nullable|numeric',
            'trips.*.tapOutAt' => 'nullable|date',
            'trips.*.tapOutLat' => 'nullable|numeric',
            'trips.*.tapOutLng' => 'nullable|numeric',
            'trips.*.distanceMeters' => 'nullable|numeric',
            'trips.*.fareMinorUnits' => 'nullable|integer',
            'trips.*.balanceBefore' => 'nullable|integer',
            'trips.*.balanceAfter' => 'nullable|integer',
            'trips.*.offlineCounter' => 'nullable|integer',
            'trips.*.transactionDateTime' => 'nullable|string|max:14',
            'trips.*.terminalTransactionSequence' => 'nullable|string|max:32',
            'trips.*.reconciliationStatus' => 'nullable|string|in:none,successMissingProof,failed,ambiguous',
            'trips.*.idempotencyKey' => 'required|string|max:128',
        ]);

        $results = [];
        $duplicates = 0;
        $created = 0;

        foreach ($validated['trips'] as $tripData) {
            DB::transaction(function () use ($tripData, $device, &$results, &$duplicates, &$created) {
                // Idempotency with upsert: if the trip already exists (same
                // idempotency key), update it with the latest data. This handles
                // the tap-in → tap-out flow where the same trip is synced twice:
                // first with tap-in data only, then with tap-out data added.
                //
                // Keys are scoped per device — a key owned by a different device
                // is a conflict, not an update.
                $existing = ValidatorTrip::where('idempotency_key', $tripData['idempotencyKey'])
                    ->lockForUpdate()
                    ->first();

                if ($existing && $existing->device_id !== $device->id) {
                    $results[] = [
                        'tripId' => $tripData['tripId'],
                        'status' => 'REJECTED',
                        'error' => 'IDEMPOTENCY_KEY_IN_USE',
                        'serverId' => null,
                    ];
                    return;
                }

                $fields = [
                    'device_id' => $device->id,
                    'trip_id' => $tripData['tripId'],
                    'card_uid' => $tripData['cardUid'],
                    'card_type' => $tripData['cardType'] ?? null,
                    'tap_in_at' => $tripData['tapInAt'] ?? null,
                    'tap_in_lat' => $tripData['tapInLat'] ?? null,
                    'tap_in_lng' => $tripData['tapInLng'] ?? null,
                    'tap_out_at' => $tripData['tapOutAt'] ?? null,
                    'tap_out_lat' => $tripData['tapOutLat'] ?? null,
                    'tap_out_lng' => $tripData['tapOutLng'] ?? null,
                    'distance_meters' => $tripData['distanceMeters'] ?? null,
                    'fare_minor_units' => $tripData['fareMinorUnits'] ?? null,
                    'fare_amount' => isset($tripData['fareMinorUnits']) ? $tripData['fareMinorUnits'] / 100 : null,
                    'balance_before' => $tripData['balanceBefore'] ?? null,
                    'balance_after' => $tripData['balanceAfter'] ?? null,
                    'offline_counter' => $tripData['offlineCounter'] ?? null,
                    'transaction_datetime' => $tripData['transactionDateTime'] ?? null,
                    'terminal_transaction_sequence' => $tripData['terminalTransactionSequence'] ?? null,
                    'reconciliation_status' => $tripData['reconciliationStatus'] ?? 'none',
                    'sync_status' => 'SYNCED',
                    'idempotency_key' => $tripData['idempotencyKey'],
                ];

                if ($existing) {
                    // Upsert: update the existing record with the latest fields.
                    // Only overwrite fields that have non-null values in the
                    // incoming payload, so a tap-in-only re-sync doesn't wipe
                    // tap-out data that was already stored.
                    $updateData = array_filter($fields, fn ($v) => $v !== null);

                    // A trip already tagged into a settlement batch is frozen:
                    // late re-syncs must not mutate the fare/balance figures the
                    // payout was computed from.
                    if ($existing->settlement_batch_id !== null) {
                        unset(
                            $updateData['fare_minor_units'],
                            $updateData['fare_amount'],
                            $updateData['balance_before'],
                            $updateData['balance_after'],
                            $updateData['distance_meters']
                        );
                    }

                    // device_id and idempotency_key are identity, not data.
                    unset($updateData['device_id'], $updateData['idempotency_key']);

                    $existing->update($updateData);
                    $duplicates++;
                    logger('[Validator] Trip updated', [
                        'trip_id' => $tripData['tripId'],
                        'card_uid' => $tripData['cardUid'],
                        'reconciliation' => $tripData['reconciliationStatus'] ?? 'none',
                        'fare' => $tripData['fareMinorUnits'] ?? null,
                        'settled' => $existing->settlement_batch_id !== null,
                    ]);
                    $results[] = [
                        'tripId' => $tripData['tripId'],
                        'status' => 'DUPLICATE',
                        'serverId' => $existing->id,
                    ];
                    return;
                }

                $trip = ValidatorTrip::create($fields);

                logger('[Validator] Trip created', [
                    'trip_id' => $tripData['tripId'],
                    'card_uid' => $tripData['cardUid'],
                    'tap_in_at' => $tripData['tapInAt'] ?? null,
                    'reconciliation' => $tripData['reconciliationStatus'] ?? 'none',
                ]);

                $created++;
                $results[] = [
                    'tripId' => $tripData['tripId'],
                    'status' => 'CREATED',
                    'serverId' => $trip->id,
                ];
            });
        }

        logger('[Validator] Trip sync summary', [
            'device_id' => $device->device_id,
            'created' => $created,
            'updated' => $duplicates,
            'results' => collect($results)->map(fn ($r) => $r['status'])->toArray(),
        ]);

        return ResponseEnvelope::success([
            'synced' => $created,
            'duplicates' => $duplicates,
            'results' => $results,
        ]);
    }

    /**
     * GET /api/v1/blocklist/delta
     * Phases 13-14: Delta blocklist export for offline validators.
     */
    public function blocklistDelta(Request $request)
    {
        $device = $this->authenticateDevice($request);

        $cursor = BlocklistCursor::firstOrCreate(['device_id' => $device->id]);

        $since = $cursor->last_synced_at ?? '1970-01-01 00:00:00';

        $entries = BlocklistEntry::where('updated_at', '>', $since)
            ->orderBy('updated_at', 'asc')
            ->limit(5000)
            ->get();

        $latest = $entries->last();
        $newCursor = $latest?->updated_at ?? $cursor->last_synced_at;

        if ($newCursor) {
            $cursor->update(['last_synced_at' => $newCursor]);
        }

        return ResponseEnvelope::success([
            'entries' => $entries->map(fn ($e) => [
                'cardUid' => $e->card_uid,
                'cardNumber' => $e->card_number,
                'reason' => $e->reason,
                'status' => $e->status,
                'effectiveAt' => $e->effective_at?->toIso8601ZuluString(),
                'liftedAt' => $e->lifted_at?->toIso8601ZuluString(),
            ])->toArray(),
            'cursor' => $newCursor?->toIso8601ZuluString(),
            'count' => $entries->count(),
        ]);
    }

    /**
     * GET /api/v1/cards/{uid}/blocklist-status
     * Phase 13: Realtime blocklist check.
     */
    public function blocklistStatus(string $uid)
    {
        $entry = BlocklistEntry::where('card_uid', $uid)->active()->first();

        return ResponseEnvelope::success([
            'blocked' => (bool) $entry,
            'reason' => $entry?->reason,
            'effectiveAt' => $entry?->effective_at?->toIso8601ZuluString(),
        ]);
    }

    /**
     * Authenticate a validator device by Bearer token.
     */
    private function authenticateDevice(Request $request, ?string $deviceId = null): ValidatorDevice
    {
        $token = $request->bearerToken();
        if (!$token) {
            throw new CardManagementError('DEVICE_UNAUTHORIZED', 401, 'Device API token required.');
        }

        $query = ValidatorDevice::where('api_token', hash('sha256', $token))
            ->where('status', 'ACTIVE');

        if ($deviceId) {
            // The URL device must exist, be ACTIVE, and own the bearer token —
            // compared in constant time against the stored hash.
            $device = ValidatorDevice::where('device_id', $deviceId)
                ->where('status', 'ACTIVE')
                ->first();
            if (!$device || !hash_equals($device->api_token, hash('sha256', $token))) {
                throw new CardManagementError('DEVICE_UNAUTHORIZED', 401, 'Invalid device credentials.');
            }
            return $device;
        }

        $device = $query->first();
        if (!$device) {
            throw new CardManagementError('DEVICE_UNAUTHORIZED', 401, 'Invalid device credentials.');
        }
        return $device;
    }
}
