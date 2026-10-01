<?php

declare(strict_types=1);

namespace App\Http\Controllers\CardManagement;

use App\Enums\CardStatus;
use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Services\Tap\TapRejected;
use App\Services\Tap\TapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Server-side wallet tap processing for E60 validators.
 *
 * Unlike the offline FMCOS EP-purse debit, this endpoint checks and
 * deducts from the server-side wallet (BalanceIn/BalanceOut ledger)
 * linked to the card's registered user. The validator calls this
 * endpoint on every tap and must be online.
 *
 * Wire contract: money fields are MINOR units (integer paisa) — the
 * card ecosystem's convention. The ledger and TapService work in
 * major units; conversion happens here at the adapter boundary.
 */
class ValidatorTapController extends Controller
{
    public function __construct(private readonly TapService $taps) {}

    /**
     * Process a tap from an E60 validator.
     *
     * Body: card_uid, device_id, lat, lon
     * Auto-detects tap-in vs tap-out based on whether the card has an
     * ongoing ride.
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

        // Resolve the bus asset from the device_id (stored as hwid on buses).
        // Asset is optional: a validator may legitimately tap without a
        // registered bus (flat fare + GPS location apply).
        $asset = \App\Models\Bus::where('hwid', $validated['device_id'])->first();

        try {
            $outcome = $this->taps->tap(
                $card,
                $asset,
                isset($validated['lat']) ? (float) $validated['lat'] : null,
                isset($validated['lon']) ? (float) $validated['lon'] : null,
            );
        } catch (TapRejected $e) {
            return $this->error($e->errorCode, $e->getMessage(), $e->httpStatus, $this->moneyToMinor($e->data));
        }

        return response()->json([
            'success' => true,
            'data' => $outcome->type === 'in'
                ? [
                    'type'        => 'in',
                    'ride_id'     => $outcome->ride->id,
                    'balance'     => self::toMinor($outcome->balance),
                    'location'    => $outcome->locationName,
                    'card_number' => $card->card_number,
                ]
                : [
                    'type'           => 'out',
                    'ride_id'        => $outcome->ride->id,
                    'fare'           => self::toMinor($outcome->fare),
                    'balance_before' => self::toMinor($outcome->balanceBefore),
                    'balance_after'  => self::toMinor($outcome->balanceAfter),
                    'shortfall'      => self::toMinor($outcome->shortfall),
                    'location'       => $outcome->locationName,
                    'card_number'    => $card->card_number,
                ],
        ], 200);
    }

    /** Convert a major-unit (rupees) amount to minor units (paisa). */
    private static function toMinor(?float $amount): int
    {
        return (int) round(($amount ?? 0) * 100);
    }

    /** Convert known money keys in error payloads to minor units. */
    private function moneyToMinor(array $data): array
    {
        foreach (['balance', 'minimum_fare', 'shortfall', 'fare'] as $key) {
            if (isset($data[$key]) && is_numeric($data[$key])) {
                $data[$key] = self::toMinor((float) $data[$key]);
            }
        }

        return $data;
    }

    private function error(string $code, string $message, int $status, array $extra = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error'   => array_merge(['code' => $code, 'message' => $message], $extra),
        ], $status);
    }
}
