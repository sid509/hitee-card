<?php

declare(strict_types=1);

namespace App\Http\Controllers\CardManagement;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Card registration bridge endpoint.
 *
 * The Node.js card-management-api (PostgreSQL) is the system of record
 * for card lifecycle (issuance, initialization, personalization). When a
 * new card is registered there, it calls this endpoint to create the
 * corresponding card record in the Laravel platform (MySQL) so that
 * validators can resolve the NFC UID to a wallet.
 *
 * This creates:
 *   - A User (wallet owner) if one does not already exist.
 *   - A Card record with card_uid mapping.
 *   - An initial BalanceIn ledger entry (opening top-up).
 */
class CardRegistrationBridgeController extends Controller
{
    public function registerCard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'card_uid'         => 'required|string|size:8',
            'card_number'      => 'required|string|max:32',
            'card_type_code'   => 'nullable|string|max:10',
            'card_type_label'  => 'nullable|string|max:32',
            'initial_balance'  => 'nullable|integer|min:0',
            'operator_name'    => 'nullable|string|max:120',
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                // Idempotent: if the card_uid already exists, return it.
                $existing = Card::where('card_uid', $validated['card_uid'])->first();
                if ($existing) {
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'card_id'     => $existing->id,
                            'card_number' => $existing->card_number,
                            'card_uid'    => $existing->card_uid,
                            'user_id'     => $existing->user_id,
                            'status'      => $existing->status,
                            'already_registered' => true,
                        ],
                    ]);
                }

                // Create a user (wallet owner) for this card.
                $operatorName = $validated['operator_name'] ?? 'Card Holder';
                $user = User::create([
                    'name'     => $operatorName,
                    'email'    => 'card_' . Str::lower($validated['card_uid']) . '@hitee.cards',
                    'password' => bcrypt(Str::random(32)),
                    'role'     => 'passenger',
                ]);

                // Generate a HITEE-format card number if the external one
                // doesn't follow the CRD- pattern.
                $cardNumber = $validated['card_number'];
                if (!str_starts_with($cardNumber, 'CRD-')) {
                    $cardNumber = 'CRD-' . strtoupper(Str::random(10));
                }

                // Generate a hardware ID.
                $hwid = 'HW-' . strtoupper(Str::random(10));

                $card = Card::create([
                    'card_number'         => $cardNumber,
                    'hwid'                => $hwid,
                    'card_uid'            => $validated['card_uid'],
                    'hitee_card_number'   => $validated['card_number'],
                    'status'              => 'active',
                    'is_currently_active' => true,
                    'is_physical'         => true,
                    'is_personalized'     => false,
                    'user_id'             => $user->id,
                ]);

                // Create an initial wallet top-up (BalanceIn).
                $initialBalance = $validated['initial_balance'] ?? 5000; // Rs 50.00 default
                if ($initialBalance > 0) {
                    DB::table('balance_ins')->insert([
                        'user_id'      => $user->id,
                        'card_id'      => $card->id,
                        'amount'       => $initialBalance,
                        'type'         => 'topup',
                        'remarks'      => 'Initial top-up at card registration',
                        'status'       => 'completed',
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                }

                Log::info('Card registered via bridge', [
                    'card_uid'    => $validated['card_uid'],
                    'card_number' => $cardNumber,
                    'user_id'     => $user->id,
                    'balance'     => $initialBalance,
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'card_id'     => $card->id,
                        'card_number' => $cardNumber,
                        'card_uid'    => $validated['card_uid'],
                        'user_id'     => $user->id,
                        'status'      => 'active',
                        'balance'     => $initialBalance,
                        'already_registered' => false,
                    ],
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error('Card registration bridge failed', [
                'card_uid' => $validated['card_uid'],
                'error'    => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'error' => [
                    'code'    => 'REGISTRATION_FAILED',
                    'message' => 'Failed to register card on platform.',
                ],
            ], 500);
        }
    }
}
