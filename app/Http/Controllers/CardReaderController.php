<?php

namespace App\Http\Controllers;

use App\Enums\CardStatus;
use App\Models\Card;
use App\Models\Bus;
use App\Models\Parking;
use App\Models\User;
use App\Models\Tap;
use App\Models\Ride;
use App\Models\BalanceIn;
use App\Models\BalanceOut;
use App\Models\MerchantIncome;
use App\Services\Tap\TapRejected;
use App\Services\Tap\TapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CardReaderController extends Controller
{
    /**
     * Card Enrollment Page
     * Tap card on reader → UID auto-fills → register in system
     */
    public function enrollPage(Request $request)
    {
        $this->authorize('create', Card::class);

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
        $this->authorize('create', Card::class);

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
            'status' => $request->filled('status')
                ? CardStatus::fromLegacy($request->status)->value
                : CardStatus::REGISTERED->value,
            'is_currently_active' => $request->boolean('is_currently_active'),
            'is_physical' => $request->boolean('is_physical', true),
            'is_personalized' => false,
        ]);

        // Add initial balance if specified
        if ($request->filled('initial_balance') && $request->initial_balance > 0) {
            app(\App\Services\LedgerService::class)->credit([
                'user_id' => $request->user_id,
                'card_id' => $card->id,
                'amount' => $request->initial_balance,
                'type' => 'manual',
                'remarks' => 'Initial balance at card enrollment',
                'created_by' => auth()->id(),
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
        $this->authorize('create', Card::class);

        $cards = Card::where('status', CardStatus::ACTIVE->value)->with('user')->latest()->get();
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
        $this->authorize('create', Card::class);

        $request->validate([
            'card_number' => 'required|string',
            'hw_id' => 'required|string',
            'lat' => 'required|numeric',
            'lon' => 'required|numeric',
        ]);

        // Run the tap through the shared engine directly — no HTTP
        // self-call, no dependence on the external route's auth surface.
        $card = Card::where('card_number', $request->card_number)->with('user')->first();
        if (!$card) {
            return response()->json([
                'status' => false,
                'message' => 'Card not found.',
            ], 404);
        }

        $asset = app(TapService::class)->resolveAsset($request->hw_id);
        if (!$asset) {
            return response()->json([
                'status' => false,
                'message' => 'Asset not found.',
            ], 404);
        }

        try {
            $outcome = app(TapService::class)->tap($card, $asset, (float) $request->lat, (float) $request->lon);
            $data = [
                'status' => true,
                'content' => [
                    'type' => $outcome->type,
                    'location' => $outcome->locationName,
                    'fare_pts' => $outcome->fare,
                    'new_balance_pts' => $outcome->balanceAfter,
                ],
            ];
            $status = 200;
        } catch (TapRejected $e) {
            $data = ['status' => false, 'message' => $e->getMessage(), 'content' => $e->data];
            $status = $e->httpStatus;
        }

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

        return response()->json($data, $status);
    }

    /**
     * Get reader serial number (for display on enrollment page)
     * The reader's serial number can be obtained via PC/SC escape command
     */
    public function getReaderInfo()
    {
        $this->authorize('create', Card::class);

        return response()->json([
            'supported' => true,
            'note' => 'Reader info is obtained by the client-side script via PC/SC. The reader serial appears as a USB device.',
        ]);
    }
}
