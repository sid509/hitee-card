<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Services\Tap\TapRejected;
use App\Services\Tap\TapService;
use Illuminate\Http\Request;

/**
 * Customer tap endpoint — thin adapter over TapService.
 *
 * Contract: apiResponse() envelope, money in major units ("pts" ≈ Rs).
 * All engine behavior (rides, fares, debits) lives in TapService so
 * this path and /api/v1/validator/tap can never diverge again.
 */
class TapController extends Controller
{
    public function __construct(protected TapService $taps) {}

    /**
     * Unified Tap Handler (Intelligently detects IN/OUT)
     *
     * Body: lat, lon, card_number, hw_id
     */
    public function processTap(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lon' => 'required|numeric',
            'card_number' => 'required|exists:cards,card_number',
            'hw_id' => 'required|string'
        ]);

        $card = Card::where('card_number', $request->card_number)->with('user')->firstOrFail();
        $asset = $this->taps->resolveAsset($request->hw_id);

        if (!$asset) return apiResponse(false, __('messages.asset_not_found'), '', 404);

        try {
            $outcome = $this->taps->tap($card, $asset, (float) $request->lat, (float) $request->lon);
        } catch (TapRejected $e) {
            return $this->reject($e);
        }

        if ($outcome->type === 'in') {
            return apiResponse(true, __('messages.tap_in_success', ['location' => $outcome->locationName]), [
                'type' => 'in',
                'ride_id' => $outcome->ride->id,
                'location' => $outcome->locationName,
            ]);
        }

        return apiResponse(true, __('messages.tap_out_success', ['location' => $outcome->locationName, 'amount' => $outcome->fare]), [
            'type' => 'out',
            'fare_pts' => $outcome->fare,
            'new_balance_pts' => $outcome->balanceAfter,
        ]);
    }

    /**
     * Render a TapRejected in this API's envelope: translated static
     * message (mobile app keys off these) + the real threshold data.
     */
    protected function reject(TapRejected $e)
    {
        $messageKey = match ($e->errorCode) {
            'INSUFFICIENT_BALANCE' => 'messages.insufficient_balance',
            'CARD_INACTIVE' => 'messages.card_inactive',
            'TAP_TOO_FAST' => 'messages.tap_too_fast',
            default => null,
        };

        return apiResponse(
            false,
            $messageKey ? __($messageKey) : $e->getMessage(),
            $e->data,
            $e->httpStatus,
        );
    }
}
