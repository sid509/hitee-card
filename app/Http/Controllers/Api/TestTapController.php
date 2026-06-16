<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Customer\TapController;
use Illuminate\Http\Request;

class TestTapController extends TapController
{
    /**
     * Test Tap Handler
     * 
     * Toggles between Tap In and Tap Out using a default test card and asset.
     * Supports both GET and POST.
     */
    public function handleTestTap(Request $request)
    {
        // Dynamically find the first available entities
        $card = \App\Models\Card::where('status', 'active')->first();
        $bus = \App\Models\Bus::first();

        if (!$card || !$bus) {
            return apiResponse(false, 'No active card or bus found for testing', '', 404);
        }

        // Mock request data using the found entities
        $request->merge([
            'lat' => 27.7172,
            'lon' => 85.3240,
            'card_number' => $card->card_number,
            'hw_id' => $bus->hwid
        ]);

        // We use the existing processTap logic which handles the toggle (ongoing ride check)
        return $this->processTap($request);
    }
}
