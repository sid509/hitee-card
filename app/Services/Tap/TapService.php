<?php

namespace App\Services\Tap;

use App\Enums\CardStatus;
use App\Models\Bus;
use App\Models\Card;
use App\Models\FareMatrix;
use App\Models\Parking;
use App\Models\Ride;
use App\Models\RouteStop;
use App\Models\Tap;
use App\Services\LedgerService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Single tap engine for every surface that debits the server-side
 * wallet: the customer API (/api/tap), the validator API
 * (/api/v1/validator/tap), and the admin bench endpoints.
 *
 * All amounts are MAJOR units (decimal rupees) end-to-end — the
 * balance_ins/balance_outs ledger convention. API adapters convert
 * for wire contracts that speak minor units.
 *
 * Behavior merged from the former duplicated controllers:
 *  - per-boarding-stop minimum-fare gate (validator path)
 *  - parking hourly-tier fares (customer path)
 *  - 2s duplicate-tap throttle + 5s tap-out cooldown (customer path)
 *  - tap-out always debits; a shortfall becomes negative balance debt
 *    (validator path)
 *  - pessimistic card + ride locking (both paths)
 *
 * Balance is owner-scoped: a card linked to a user spends from the
 * user's wallet pool; an orphaned card spends from its own ledger.
 */
class TapService
{
    /** Same stop / unresolved matrix flat fare (rupees). */
    public const FARE_SAME_STOP = 15.00;

    /** Flat fare when no fare matrix entry exists (rupees). */
    public const FARE_FALLBACK = 20.00;

    /** Flat fare when the asset cannot be resolved (rupees). */
    public const FARE_NO_ASSET = 20.00;

    public function __construct(private readonly LedgerService $ledger) {}

    /**
     * Process a tap: detects tap-in vs tap-out from the card's ongoing
     * ride, inside a transaction with pessimistic locking.
     *
     * @param Card              $card   resolved card (adapter validates
     *                                  existence, status, card-number check)
     * @param Bus|Parking|null  $asset  resolved bus/parking, or null when
     *                                  the caller tolerates asset-less taps
     * @throws TapRejected
     */
    public function tap(Card $card, Bus|Parking|null $asset, ?float $lat, ?float $lon): TapOutcome
    {
        if ($card->status !== CardStatus::ACTIVE->value) {
            throw new TapRejected(
                'CARD_INACTIVE',
                'Card is '.$card->status.'.',
                403,
            );
        }

        $this->guardDuplicateTap($card, $asset);

        return DB::transaction(function () use ($card, $asset, $lat, $lon) {
            $card = Card::where('id', $card->id)->lockForUpdate()->firstOrFail();

            $ongoingRide = Ride::where('card_id', $card->id)
                ->where('status', 'ongoing')
                ->lockForUpdate()
                ->first();

            if ($ongoingRide) {
                // A tap-out arriving <5s after tap-in is almost always the
                // reader double-firing, not a completed journey.
                if ($ongoingRide->created_at->diffInSeconds(now()) < 5) {
                    throw new TapRejected(
                        'TAP_TOO_FAST',
                        'Tap-out too soon after tap-in. Retry in a few seconds.',
                        422,
                        ['retry_after' => 5],
                    );
                }

                return $this->tapOut($ongoingRide, $card, $asset, $lat, $lon);
            }

            return $this->tapIn($card, $asset, $lat, $lon);
        });
    }

    /**
     * Resolve an asset from a hardware/device id: buses carry it as
     * `hwid` (the validator's device_id doubles as the bus hwid), and
     * parking lots by primary key. Returns null when nothing matches —
     * callers decide whether that is an error.
     */
    public function resolveAsset(string $key): Bus|Parking|null
    {
        return Bus::where('hwid', $key)->first() ?? Parking::where('id', $key)->first();
    }

    private function guardDuplicateTap(Card $card, Bus|Parking|null $asset): void
    {
        $key = 'tap_throttle_'.$card->id.'_'.($asset?->getKey() ?? 'none');
        if (Cache::has($key)) {
            throw new TapRejected(
                'TAP_TOO_FAST',
                'Duplicate tap. Retry in a few seconds.',
                422,
                ['retry_after' => 2],
            );
        }
        Cache::put($key, true, 2);
    }

    private function tapIn(Card $card, Bus|Parking|null $asset, ?float $lat, ?float $lon): TapOutcome
    {
        $user = $card->user;
        $balance = $this->walletBalance($card, $user);

        $location = $this->resolveLocation($lat, $lon, $asset);
        $minimumFare = $this->minimumFareFromStop($asset, $location['id']);

        if ($balance < $minimumFare) {
            $stopLabel = $location['id'] ? "stop \"{$location['name']}\"" : 'current location';
            $reason = $balance < 0
                ? "Wallet balance is {$balance} points (negative — outstanding debt from a previous ride). Minimum fare from {$stopLabel} is {$minimumFare} points."
                : "Wallet balance is {$balance} points; minimum fare from {$stopLabel} is {$minimumFare} points required to board.";

            throw new TapRejected('INSUFFICIENT_BALANCE', $reason, 402, [
                'balance' => $balance,
                'minimum_fare' => $minimumFare,
                'boarding_stop' => $location['name'],
            ]);
        }

        $tap = Tap::create([
            'user_id' => $user?->id,
            'card_id' => $card->id,
            'merchant_id' => $asset?->merchant_id,
            'reference_id' => $asset?->id,
            'reference_type' => $asset ? get_class($asset) : null,
            'type' => 'in',
            'stop_id' => $location['id'],
            'resolved_location_name' => $location['name'],
            'latitude' => $lat,
            'longitude' => $lon,
        ]);

        $ride = Ride::create([
            'user_id' => $user?->id,
            'card_id' => $card->id,
            'merchant_id' => $asset?->merchant_id,
            'reference_id' => $asset?->id,
            'reference_type' => $asset ? get_class($asset) : null,
            'tap_in_id' => $tap->id,
            'status' => 'ongoing',
        ]);

        logActivity('tap_in', "Tapped in at {$location['name']} on ".($asset?->name ?? 'unknown asset'),
            ['ride_id' => $ride->id], $user?->id);
        Log::info('Tap-in', [
            'card_uid' => $card->card_uid,
            'ride_id' => $ride->id,
            'balance' => $balance,
            'minimum_fare' => $minimumFare,
            'boarding_stop' => $location['name'],
        ]);

        return new TapOutcome(
            type: 'in',
            ride: $ride,
            tap: $tap,
            locationId: $location['id'],
            locationName: $location['name'],
            balance: (float) $balance,
            minimumFare: (float) $minimumFare,
        );
    }

    private function tapOut(Ride $ride, Card $card, Bus|Parking|null $asset, ?float $lat, ?float $lon): TapOutcome
    {
        $user = $ride->user;
        $location = $this->resolveLocation($lat, $lon, $asset);

        $tapOut = Tap::create([
            'user_id' => $user?->id,
            'card_id' => $ride->card_id,
            'merchant_id' => $asset?->merchant_id,
            'reference_id' => $asset?->id,
            'reference_type' => $asset ? get_class($asset) : null,
            'type' => 'out',
            'stop_id' => $location['id'],
            'resolved_location_name' => $location['name'],
            'latitude' => $lat,
            'longitude' => $lon,
        ]);

        $fareAmount = $this->calculateFare($ride, $tapOut, $asset);

        $balanceBefore = (float) $this->walletBalance($card, $user);
        $shortfall = max(0, $fareAmount - $balanceBefore);

        // Always debit the full fare — an uncovered remainder becomes
        // negative balance (debt) and blocks the next tap-in until the
        // wallet is topped up past it.
        if ($fareAmount > 0) {
            $isBus = $ride->reference_type === Bus::class;
            $remarks = "Journey #{$ride->id} completed. From {$ride->tapIn->resolved_location_name} to {$location['name']}"
                .($shortfall > 0 ? ". Shortfall of {$shortfall} points recorded as debt." : '');

            $this->ledger->debitWithIncome([
                'user_id' => $user?->id,
                'card_id' => $ride->card_id,
                'merchant_id' => $ride->merchant_id,
                'amount' => $fareAmount,
                'type' => $isBus ? 'fare_deduction' : 'parking',
                'remarks' => $remarks,
                'reference_id' => $ride->reference_id,
                'reference_type' => $ride->reference_type,
            ], $isBus ? 'fare' : 'parking');
        }

        $ride->update([
            'tap_out_id' => $tapOut->id,
            'fare_amount' => $fareAmount,
            'status' => 'completed',
        ]);

        $balanceAfter = (float) $this->walletBalance($card, $user);

        logActivity('ride_completed', "Ride finished. Fare: {$fareAmount} pts", [
            'ride_id' => $ride->id,
            'fare_pts' => $fareAmount,
            'start' => $ride->tapIn->resolved_location_name,
            'end' => $location['name'],
        ], $user?->id);
        Log::info('Tap-out', [
            'card_uid' => $card->card_uid,
            'ride_id' => $ride->id,
            'fare' => $fareAmount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'shortfall' => $shortfall,
        ]);

        return new TapOutcome(
            type: 'out',
            ride: $ride,
            tap: $tapOut,
            locationId: $location['id'],
            locationName: $location['name'],
            balance: $balanceAfter,
            fare: (float) $fareAmount,
            balanceBefore: $balanceBefore,
            balanceAfter: $balanceAfter,
            shortfall: (float) $shortfall,
        );
    }

    /**
     * Balance of the wallet that owns this card's funds: the linked
     * user's pool when present (topups are user-scoped), otherwise the
     * card's own ledger.
     */
    private function walletBalance(Card $card, $user): float
    {
        return (float) ($user ? $user->balance() : $card->balance());
    }

    /**
     * Cheapest fare departing from the given boarding stop on the
     * asset's active fare matrix — the minimum a rider must be able to
     * cover from where they actually boarded.
     */
    private function minimumFareFromStop(Bus|Parking|null $asset, ?int $stopId): float
    {
        if ($asset instanceof Bus && $asset->active_fare_id && $stopId) {
            $minFare = FareMatrix::where('fare_id', $asset->active_fare_id)
                ->where('from_stop_id', $stopId)
                ->min('amount');

            if ($minFare !== null) {
                return (float) $minFare;
            }
        }

        return self::FARE_SAME_STOP;
    }

    private function resolveLocation(?float $lat, ?float $lon, Bus|Parking|null $asset): array
    {
        $haversine = '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))';

        if ($asset instanceof Bus && $lat !== null && $lon !== null) {
            $stop = RouteStop::where('route_id', $asset->route_id)
                ->select('*')
                ->selectRaw("$haversine AS distance", [$lat, $lon, $lat])
                ->orderBy('distance')
                ->first();

            if (! $stop) {
                return ['id' => null, 'name' => "GPS: $lat, $lon"];
            }

            $name = $stop->stop_name;
            if ($stop->distance > 0.5) {
                $name = 'Near '.$name;
            }

            return ['id' => $stop->id, 'name' => $name];
        }

        if ($asset instanceof Parking) {
            return ['id' => $asset->id, 'name' => $asset->name];
        }

        if ($lat !== null && $lon !== null) {
            return ['id' => null, 'name' => "GPS: $lat, $lon"];
        }

        return ['id' => null, 'name' => 'Unknown'];
    }

    private function calculateFare(Ride $ride, Tap $tapOut, Bus|Parking|null $asset): float
    {
        $tapIn = $ride->tapIn;

        if ($ride->reference_type === Bus::class) {
            return $this->calculateBusFare($ride, $tapIn, $tapOut);
        }

        if ($ride->reference_type === Parking::class) {
            return $this->calculateParkingFare($tapIn, $tapOut, $ride->reference_id);
        }

        return self::FARE_NO_ASSET;
    }

    private function calculateBusFare(Ride $ride, ?Tap $tapIn, Tap $tapOut): float
    {
        if (! $tapIn?->stop_id || ! $tapOut->stop_id || $tapIn->stop_id === $tapOut->stop_id) {
            return self::FARE_SAME_STOP;
        }

        $bus = Bus::find($ride->reference_id);
        $matrix = $bus?->active_fare_id
            ? FareMatrix::where('fare_id', $bus->active_fare_id)
                ->where('from_stop_id', $tapIn->stop_id)
                ->where('to_stop_id', $tapOut->stop_id)
                ->first()
            : null;

        return $matrix ? (float) $matrix->amount : self::FARE_FALLBACK;
    }

    private function calculateParkingFare(?Tap $tapIn, Tap $tapOut, $parkingId): float
    {
        $parking = Parking::with('fees')->find($parkingId);
        if (! $parking || ! $tapIn) {
            return self::FARE_FALLBACK;
        }

        $durationHours = max(1, (int) ceil($tapIn->created_at->diffInMinutes($tapOut->created_at) / 60));

        $fees = $parking->fees;
        if ($fees->isEmpty()) {
            return self::FARE_FALLBACK;
        }

        $totalFare = 0;
        for ($i = 1; $i <= $durationHours; $i++) {
            $feeTier = $fees[$i - 1] ?? $fees->last();
            $totalFare += $feeTier->price_pts;
        }

        return (float) $totalFare;
    }
}
