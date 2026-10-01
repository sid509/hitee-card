<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Bus;
use App\Models\Parking;
use App\Models\User;
use App\Models\Tap;
use App\Models\Ride;
use App\Models\BalanceIn;
use App\Models\BalanceOut;
use App\Models\MerchantIncome;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class CardReaderController extends Controller
{
    /**
     * Card Enrollment Page
     * Tap card on reader → UID auto-fills → register in system
     */
    public function enrollPage(Request $request)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        $recentCards = Card::with(['user', 'subscriptionModels'])
            ->latest()
            ->limit(10)
            ->get();

        $users = User::whereHas('roles', function($q){ $q->where('slug', 'customers'); })->get(['id', 'name', 'email']);

        return view('modules.card-reader.enroll', compact('recentCards', 'users'));
    }

    /**
     * Register a card from the reader (AJAX from enrollment page)
     */
    public function enrollStore(Request $request)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        $request->validate([
            'card_number' => 'required|string|unique:cards,card_number',
            'hwid' => 'nullable|string|unique:cards,hwid',
            'user_id' => 'nullable|exists:users,id',
            'status' => 'required|in:active,inactive,blocked',
            'is_currently_active' => 'boolean',
            'is_physical' => 'boolean',
            'initial_balance' => 'nullable|numeric|min:0',
        ]);

        // Auto-generate hwid if not provided (from card UID)
        $hwid = $request->hwid ?: 'CARD-' . strtoupper($request->card_number);

        // If setting as active, deactivate other cards for this user
        if ($request->is_currently_active && $request->user_id) {
            Card::where('user_id', $request->user_id)->update(['is_currently_active' => false]);
        }

        $card = Card::create([
            'card_number' => $request->card_number,
            'hwid' => $hwid,
            'user_id' => $request->user_id,
            'status' => match ($request->status) {
                'active' => 'ACTIVE',
                'inactive' => 'INACTIVE',
                'blocked' => 'BLOCKED',
                default => 'REGISTERED',
            },
            'is_currently_active' => $request->boolean('is_currently_active'),
            'is_physical' => $request->boolean('is_physical', true),
            'is_personalized' => false,
        ]);

        // Add initial balance if specified
        if ($request->filled('initial_balance') && $request->initial_balance > 0) {
            BalanceIn::create([
                'user_id' => $request->user_id,
                'card_id' => $card->id,
                'amount' => $request->initial_balance,
                'type' => 'manual',
                'remarks' => 'Initial balance at card enrollment',
                'created_by' => auth()->id(),
                'status' => 'completed',
                'gateway_name' => 'System',
                'transaction_id' => 'ENROLL-' . time(),
            ]);
        }

        logActivity('card_enrolled', "Card enrolled via reader: {$card->card_number}", [
            'card_id' => $card->id,
            'card_number' => $card->card_number,
            'user_id' => $request->user_id,
        ]);

        return response()->json([
            'status' => true,
            'message' => "Card {$card->card_number} enrolled successfully!",
            'card' => [
                'id' => $card->id,
                'card_number' => $card->card_number,
                'hwid' => $card->hwid,
                'status' => $card->status,
                'user' => $card->user ? $card->user->name : 'Unassigned',
            ]
        ]);
    }

    /**
     * Check if a card UID is already registered (AJAX lookup)
     */
    public function checkUid(Request $request)
    {
        $request->validate(['card_number' => 'required|string']);

        $card = Card::where('card_number', $request->card_number)->with('user')->first();

        if ($card) {
            return response()->json([
                'exists' => true,
                'card' => [
                    'id' => $card->id,
                    'card_number' => $card->card_number,
                    'status' => $card->status,
                    'user' => $card->user ? $card->user->name : 'Unassigned',
                    'balance' => $card->user ? $card->user->balance() : $card->balance(),
                    'is_currently_active' => $card->is_currently_active,
                ]
            ]);
        }

        return response()->json(['exists' => false]);
    }

    /**
     * Tap Test Page
     * Tap card → see what happens in the system (tap-in/tap-out, fare, balance)
     */
    public function tapTestPage(Request $request)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        $cards = Card::where('status', 'ACTIVE')->with('user')->latest()->get();
        $buses = Bus::with('route')->where('status', 'active')->get();
        $parkings = Parking::where('status', 'active')->get();

        $recentTaps = Tap::with(['card.user', 'reference'])->latest()->limit(20)->get();

        return view('modules.card-reader.tap-test', compact('cards', 'buses', 'parkings', 'recentTaps'));
    }

    /**
     * Process a tap from the tap test page (AJAX)
     * This calls the internal API endpoint
     */
    public function processTapTest(Request $request)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        $request->validate([
            'card_number' => 'required|string',
            'hw_id' => 'required|string',
            'lat' => 'required|numeric',
            'lon' => 'required|numeric',
        ]);

        // Call the internal /api/tap endpoint
        $apiUrl = config('app.url') . '/api/tap';

        $response = Http::timeout(10)->post($apiUrl, [
            'card_number' => $request->card_number,
            'hw_id' => $request->hw_id,
            'lat' => $request->lat,
            'lon' => $request->lon,
        ]);

        $data = $response->json();

        // Enrich with card details
        $card = Card::where('card_number', $request->card_number)->with('user')->first();
        if ($card) {
            $data['card_details'] = [
                'id' => $card->id,
                'card_number' => $card->card_number,
                'user' => $card->user ? $card->user->name : 'Unassigned',
                'balance' => $card->user ? $card->user->balance() : $card->balance(),
            ];

            // Get latest tap record for this card
            $latestTap = Tap::where('card_id', $card->id)->latest()->first();
            if ($latestTap) {
                $data['tap_record'] = [
                    'id' => $latestTap->id,
                    'type' => $latestTap->type,
                    'location' => $latestTap->resolved_location_name,
                    'time' => $latestTap->created_at->toDateTimeString(),
                ];
            }

            // Get latest ride
            $latestRide = Ride::where('card_id', $card->id)->latest()->first();
            if ($latestRide) {
                $data['ride_record'] = [
                    'id' => $latestRide->id,
                    'status' => $latestRide->status,
                    'fare' => $latestRide->fare_amount,
                    'asset_type' => str_replace('App\\Models\\', '', $latestRide->reference_type),
                ];
            }
        }

        return response()->json($data, $response->status());
    }

    /**
     * Get reader serial number (for display on enrollment page)
     * The reader's serial number can be obtained via PC/SC escape command
     */
    public function getReaderInfo()
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        return response()->json([
            'supported' => true,
            'note' => 'Reader info is obtained by the client-side script via PC/SC. The reader serial appears as a USB device.',
        ]);
    }
}
