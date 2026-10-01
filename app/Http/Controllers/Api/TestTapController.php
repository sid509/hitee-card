<?php

namespace App\Http\Controllers\Api;

use App\Enums\CardStatus;
use App\Http\Controllers\Controller;
use App\Models\Bus;
use App\Models\Card;
use App\Services\Tap\TapRejected;
use App\Services\Tap\TapService;
use Illuminate\Http\Request;

class TestTapController extends Controller
{
    /**
     * Test Tap Handler
     *
     * Toggles between Tap In and Tap Out using a default test card and asset.
     * Supports both GET and POST. Requires an authenticated super-admin
     * token — this endpoint debits a real card through the wallet engine.
     */
    public function handleTestTap(Request $request, TapService $taps)
    {
        if (!$request->user()?->hasRole('super-admin')) {
            return response("FAILED: Super-admin token required", 403)
                ->header('Content-Type', 'text/plain');
        }

        // Dynamically find the first available entities
        $card = Card::where('status', CardStatus::ACTIVE->value)->first();
        $bus = Bus::first();

        if (!$card || !$bus) {
            return response("FAILED: No active card or bus found for testing", 404)
                ->header('Content-Type', 'text/plain');
        }

        try {
            $outcome = $taps->tap($card, $bus, 27.7172, 85.3240);
        } catch (TapRejected $e) {
            return response("FAILED: {$e->getMessage()}", $e->httpStatus)
                ->header('Content-Type', 'text/plain');
        }

        $output = $outcome->type === 'in'
            ? 'SUCCESS: '.__('messages.tap_in_success', ['location' => $outcome->locationName])
            : 'SUCCESS: '.__('messages.tap_out_success', ['location' => $outcome->locationName, 'amount' => $outcome->fare])
                ." | Balance: {$outcome->balanceAfter} pts";

        return response($output, 200)
            ->header('Content-Type', 'text/plain');
    }
}
