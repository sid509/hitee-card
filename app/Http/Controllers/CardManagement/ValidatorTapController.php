<?php

declare(strict_types=1);

namespace App\Http\Controllers\CardManagement;

use App\Enums\CardStatus;
use App\Http\Controllers\Controller;
use App\Models\Bus;
use App\Models\Card;
use App\Models\Ride;
use App\Models\Tap;
use App\Models\RouteStop;
use App\Models\FareMatrix;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Server-side wallet tap processing for E60 validators.
 *
 * Unlike the offline FMCOS EP-purse debit, this controller checks and
 * deducts from the server-side wallet (BalanceIn/BalanceOut ledger)
 * linked to the card's registered user. The validator calls this
 * endpoint on every tap and must be online.
 */
class ValidatorTapController extends Controller
{
    /**
     * Process a tap from an E60 validator.
     *
     * Body: card_uid, device_id, lat, lon
     * The controller auto-detects tap-in vs tap-out based on whether
     * the card has an ongoing ride.
     */
    public function processTap(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'card_uid'     => 'required|string',
            'device_id'    => 'required|string',
            'card_number'  => 'nullable|string|max:32',
            'lat'          => 'nullable|numeric',
            'lon'          => 'nullable|numeric',
        ]);

        $card = Card::where('card_uid', $validated['card_uid'])->first();
        if (!$card) {
            return $this->error('CARD_NOT_FOUND', 'Card not registered on platform.', 404);
        }

        if ($card->status !== CardStatus::ACTIVE->value) {
            return $this->error('CARD_INACTIVE', 'Card is ' . $card->status . '.', 403);
        }

        // If the validator provides a card_number (read from EF 0005 and
        // DCCK-authenticated), verify it matches the registered Hitee card
        // number (the 16-digit number written during card initialization).
        // This prevents UID cloning — a cloned card would fail DCCK auth
        // before reaching this point, but we also verify here as defense
        // in depth.
        if (!empty($validated['card_number'])) {
            if ($card->hitee_card_number !== $validated['card_number']) {
                Log::warning('Card number mismatch', [
                    'card_uid'          => $validated['card_uid'],
                    'registered_number' => $card->hitee_card_number,
                    'presented_number'  => $validated['card_number'],
                ]);
                return $this->error(
                    'CARD_NUMBER_MISMATCH',
                    'Card verification failed. Card number does not match registration.',
                    403
                );
            }
        }

        $user = $card->user;
        $deviceId = $validated['device_id'];

        // Resolve the bus asset from the device_id (stored as hwid on buses).
        $asset = Bus::where('hwid', $deviceId)->first();

        return DB::transaction(function () use ($card, $user, $deviceId, $asset, $validated) {
            // Lock the card row to prevent concurrent tap races.
            $card = Card::where('id', $card->id)->lockForUpdate()->firstOrFail();

            // Check for an ongoing ride for this card.
            $ongoingRide = Ride::where('card_id', $card->id)
                ->where('status', 'ongoing')
                ->lockForUpdate()
                ->first();

            if ($ongoingRide) {
                return $this->handleTapOut($validated, $ongoingRide, $card, $user, $asset);
            }

            return $this->handleTapIn($validated, $card, $user, $asset);
        });
    }

    /**
     * Tap-in: resolve the boarding stop from GPS, check that the user's
     * balance covers at least the cheapest fare from that specific stop
     * to the next stop, then create tap + ride records.
     *
     * The user is rejected if their balance is negative (they owe debt from
     * a previous ride) or if their balance is less than the minimum fare
     * from the stop where they actually boarded.
     */
    private function handleTapIn(array $request, Card $card, $user, $asset): JsonResponse
    {
        $balance = $user ? $user->balance() : $card->balance();

        // Resolve the boarding stop from GPS coordinates first.
        $location = $this->resolveLocation($request['lat'] ?? null, $request['lon'] ?? null, $asset);

        // Get the minimum fare FROM the specific boarding stop.
        // This is the cheapest possible ride from where the user actually
        // boarded — not the cheapest fare on the entire route.
        $minimumFare = $this->getMinimumFareFromStop($asset, $location['id']);

        if ($balance < $minimumFare) {
            $stopLabel = $location['id'] ? "stop \"{$location['name']}\"" : 'current location';
            $reason = $balance < 0
                ? "Wallet balance is {$balance} points (negative — outstanding debt from a previous ride). Minimum fare from {$stopLabel} is {$minimumFare} points."
                : "Wallet balance is {$balance} points; minimum fare from {$stopLabel} is {$minimumFare} points required to board.";

            return $this->error(
                'INSUFFICIENT_BALANCE',
                $reason,
                402,
                ['balance' => $balance, 'minimum_fare' => $minimumFare, 'boarding_stop' => $location['name']]
            );
        }

        $tap = Tap::create([
            'user_id'   => $user?->id,
            'card_id'   => $card->id,
            'merchant_id' => $asset?->merchant_id,
            'reference_id'   => $asset?->id,
            'reference_type' => $asset ? get_class($asset) : null,
            'type'      => 'in',
            'stop_id'   => $location['id'],
            'resolved_location_name' => $location['name'],
            'latitude'  => $request['lat'] ?? null,
            'longitude' => $request['lon'] ?? null,
        ]);

        $ride = Ride::create([
            'user_id'   => $user?->id,
            'card_id'   => $card->id,
            'merchant_id' => $asset?->merchant_id,
            'reference_id'   => $asset?->id,
            'reference_type' => $asset ? get_class($asset) : null,
            'tap_in_id' => $tap->id,
            'status'    => 'ongoing',
        ]);

        Log::info('Validator tap-in', [
            'card_uid'     => $card->card_uid,
            'ride_id'      => $ride->id,
            'balance'      => $balance,
            'minimum_fare' => $minimumFare,
            'boarding_stop'=> $location['name'],
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'type'        => 'in',
                'ride_id'     => $ride->id,
                'balance'     => $balance,
                'location'    => $location['name'],
                'card_number' => $card->card_number,
            ],
        ], 200);
    }

    /**
     * Tap-out: calculate fare, deduct from server-side wallet, close ride.
     *
     * If the wallet cannot cover the full fare, the available balance is
     * deducted and the remainder is recorded as negative balance (debt).
     * The ride always completes — the user will be unable to tap in again
     * until they top up to cover the debt plus the route's minimum fare.
     */
    private function handleTapOut(array $request, Ride $ride, Card $card, $user, $asset): JsonResponse
    {
        $location = $this->resolveLocation($request['lat'] ?? null, $request['lon'] ?? null, $asset);

        $tapOut = Tap::create([
            'user_id'   => $user?->id,
            'card_id'   => $ride->card_id,
            'merchant_id' => $asset?->merchant_id,
            'reference_id'   => $asset?->id,
            'reference_type' => $asset ? get_class($asset) : null,
            'type'      => 'out',
            'stop_id'   => $location['id'],
            'resolved_location_name' => $location['name'],
            'latitude'  => $request['lat'] ?? null,
            'longitude' => $request['lon'] ?? null,
        ]);

        // Calculate fare.
        $fareAmount = $this->calculateFare($ride, $tapOut, $asset);

        // Check the wallet balance before deduction.
        $balanceBefore = $user ? $user->balance() : $card->balance();
        $shortfall = max(0, $fareAmount - $balanceBefore);

        // Always deduct the full fare. If the balance is insufficient,
        // the wallet goes negative (debt). The BalanceIn/BalanceOut ledger
        // naturally supports this — balance() will return a negative value.
        if ($fareAmount > 0) {
            $remarks = $shortfall > 0
                ? "Journey #{$ride->id} completed. From {$ride->tapIn->resolved_location_name} to {$location['name']}. Shortfall of {$shortfall} points recorded as debt."
                : "Journey #{$ride->id} completed. From {$ride->tapIn->resolved_location_name} to {$location['name']}";

            app(\App\Services\LedgerService::class)->debitWithIncome([
                'user_id'   => $user?->id,
                'card_id'   => $ride->card_id,
                'merchant_id' => $ride->merchant_id,
                'amount'    => $fareAmount,
                'type'      => 'fare_deduction',
                'remarks'   => $remarks,
                'reference_id'   => $ride->reference_id,
                'reference_type' => $ride->reference_type,
            ], 'fare');
        }

        $ride->update([
            'tap_out_id'  => $tapOut->id,
            'fare_amount' => $fareAmount,
            'status'      => 'completed',
        ]);

        $balanceAfter = $user ? $user->balance() : $card->balance();

        Log::info('Validator tap-out', [
            'card_uid'      => $card->card_uid,
            'ride_id'       => $ride->id,
            'fare'          => $fareAmount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'shortfall'     => $shortfall,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'type'           => 'out',
                'ride_id'        => $ride->id,
                'fare'           => $fareAmount,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'shortfall'      => $shortfall > 0 ? $shortfall : 0,
                'location'       => $location['name'],
                'card_number'    => $card->card_number,
            ],
        ], 200);
    }

    /**
     * Calculate the fare for a completed ride.
     * FareMatrix amount is stored in rupees (decimal); the wallet ledger
     * stores points/paise. Convert rupees to points by multiplying by 100.
     * Falls back to a flat Rs 20.00 (2000 points) if no fare matrix.
     */
    private function calculateFare(Ride $ride, Tap $tapOut, $asset): float
    {
        $tapIn = $ride->tapIn;

        if ($asset instanceof Bus && $tapIn && $tapOut) {
            if (!$tapIn->stop_id || !$tapOut->stop_id || $tapIn->stop_id == $tapOut->stop_id) {
                return 1500; // Same stop flat fare: Rs 15.00
            }

            $matrix = FareMatrix::where('fare_id', $asset->active_fare_id)
                ->where('from_stop_id', $tapIn->stop_id)
                ->where('to_stop_id', $tapOut->stop_id)
                ->first();

            return $matrix ? ((float) $matrix->amount) * 100 : 2000; // Rs 20.00
        }

        // Flat fare fallback (no bus/route resolved).
        return 2000; // Rs 20.00
    }

    /**
     * Get the minimum fare from a specific boarding stop.
     *
     * Queries the FareMatrix for the cheapest fare FROM the given stop
     * to any other stop on the same route. This ensures the user has
     * enough balance to cover at least the shortest ride from where they
     * actually boarded — not the cheapest fare on the entire route.
     *
     * FareMatrix amount is stored in rupees; the wallet ledger uses points
     * (paise). Convert by multiplying by 100.
     *
     * Falls back to the same-stop flat fare (Rs 15.00 = 1500 points) if:
     * - No bus/fare matrix is configured
     * - The boarding stop could not be resolved (no GPS)
     * - No fare entries exist from that stop
     */
    private function getMinimumFareFromStop($asset, ?int $stopId): float
    {
        if ($asset instanceof Bus && $asset->active_fare_id && $stopId) {
            $minFare = FareMatrix::where('fare_id', $asset->active_fare_id)
                ->where('from_stop_id', $stopId)
                ->min('amount');
            if ($minFare !== null) {
                return ((float) $minFare) * 100;
            }
        }

        // Fallback: same-stop flat fare is the cheapest possible fare.
        return 1500; // Rs 15.00
    }

    /**
     * Resolve the nearest stop name from GPS coordinates.
     */
    private function resolveLocation(?float $lat, ?float $lon, $asset): array
    {
        if ($lat === null || $lon === null) {
            return ['id' => null, 'name' => 'Unknown'];
        }

        if ($asset instanceof Bus) {
            $haversine = "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))";

            $stop = RouteStop::where('route_id', $asset->route_id)
                ->select('*')
                ->selectRaw("$haversine AS distance", [$lat, $lon, $lat])
                ->orderBy('distance')
                ->first();

            if (!$stop) {
                return ['id' => null, 'name' => "GPS: $lat, $lon"];
            }

            $name = $stop->stop_name;
            if ($stop->distance > 0.5) {
                $name = "Near " . $name;
            }

            return ['id' => $stop->id, 'name' => $name];
        }

        return ['id' => null, 'name' => "GPS: $lat, $lon"];
    }

    private function error(string $code, string $message, int $status, array $extra = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code'    => $code,
                'message' => $message,
            ],
            'data' => $extra,
        ], $status);
    }

    /**
     * Read-only JSON tap ledger for monitoring recent validator taps.
     * Returns the latest 50 taps with card, ride, and location info.
     */
    public function tapLedger(Request $request): JsonResponse
    {
        $limit = min((int) $request->query('limit', 50), 200);

        $taps = Tap::with(['card', 'user'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $data = $taps->map(function (Tap $tap) {
            $ride = Ride::where('tap_in_id', $tap->id)
                ->orWhere('tap_out_id', $tap->id)
                ->first();

            return [
                'id'         => $tap->id,
                'type'       => $tap->type,
                'card_number'=> $tap->card?->card_number,
                'card_uid'   => $tap->card?->card_uid,
                'user'       => $tap->user?->name,
                'latitude'   => $tap->latitude,
                'longitude'  => $tap->longitude,
                'location'   => $tap->resolved_location_name,
                'stop_id'    => $tap->stop_id,
                'fare'       => $ride?->fare_amount !== null
                    ? number_format((float) $ride->fare_amount / 100, 2, '.', '')
                    : null,
                'ride_status'=> $ride?->status,
                'ride_id'    => $ride?->id,
                'created_at'  => $tap->created_at?->toDateTimeString(),
            ];
        });

        return response()->json([
            'success' => true,
            'count'   => $data->count(),
            'data'    => $data,
        ]);
    }
}
