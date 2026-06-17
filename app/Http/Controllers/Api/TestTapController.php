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
            return response("FAILED: No active card or bus found for testing", 404)
                ->header('Content-Type', 'text/plain');
        }

        // Mock request data using the found entities
        $request->merge([
            'lat' => 27.7172,
            'lon' => 85.3240,
            'card_number' => $card->card_number,
            'hw_id' => $bus->hwid
        ]);

        // We use the existing processTap logic which handles the toggle (ongoing ride check)
        $jsonResponse = $this->processTap($request);
        $data = $jsonResponse->getData();

        $status = $data->status ? 'SUCCESS' : 'FAILED';
        $output = "{$status}: {$data->message}";

        // If tap out, append balance for better testing visibility (Fare is already in the message)
        if ($data->status && isset($data->content->type) && $data->content->type === 'out') {
            $output .= " | Balance: {$data->content->new_balance_pts} pts";
        }

        return response($output, $jsonResponse->getStatusCode())
            ->header('Content-Type', 'text/plain');
    }
}
