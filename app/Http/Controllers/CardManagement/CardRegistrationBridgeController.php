<?php

declare(strict_types=1);

namespace App\Http\Controllers\CardManagement;

use App\Enums\CardStatus;
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
 * Called by the Node.js card-issuer platform sync after a card is
 * registered. The cards table is now the single registry for both the
 * card-management lifecycle and the customer wallet, so this endpoint
 * is a find-or-link:
 *   - If the card already exists (e.g. registered via /v1/cards/register),
 *     it is linked to a wallet owner and activated for wallet use.
 *   - If it does not exist yet, the card record is created here.
 *
 * In both cases this ensures the card has:
 *   - A User (wallet owner).
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
                $initialBalance = $validated['initial_balance'] ?? 5000; // Rs 50.00 default

                $card = Card::where('card_uid', $validated['card_uid'])
                    ->lockForUpdate()
                    ->first();

                if ($card && $card->user_id) {
                    // Fully registered already — idempotent replay.
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'card_id'     => $card->id,
                            'card_number' => $card->card_number,
                            'card_uid'    => $card->card_uid,
                            'user_id'     => $card->user_id,
                            'status'      => $card->status,
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

                $hwid = 'HW-' . strtoupper(Str::random(10));

                if ($card) {
                    // Card exists in the registry (registered via the CM
                    // flow) — link the wallet owner and activate it.
                    $card->update([
                        'user_id'             => $user->id,
                        'hwid'                => $card->hwid ?? $hwid,
                        'hitee_card_number'   => $card->hitee_card_number ?? $validated['card_number'],
                        'status'              => CardStatus::ACTIVE->value,
                        'is_currently_active' => true,
                        'is_physical'         => true,
                    ]);
                } else {
                    // Generate a HITEE-format card number if the external one
                    // doesn't follow the CRD- pattern.
                    $cardNumber = $validated['card_number'];
                    if (!str_starts_with($cardNumber, 'CRD-')) {
                        $cardNumber = 'CRD-' . strtoupper(Str::random(10));
                    }

                    $card = Card::create([
                        'card_number'         => $cardNumber,
                        'hwid'                => $hwid,
                        'card_uid'            => $validated['card_uid'],
                        'hitee_card_number'   => $validated['card_number'],
                        'card_type_code'      => $validated['card_type_code'] ?? '0100',
                        'card_type_label'     => $validated['card_type_label'] ?? 'STANDARD',
                        'status'              => CardStatus::ACTIVE->value,
                        'is_currently_active' => true,
                        'is_physical'         => true,
                        'is_personalized'     => false,
                        'user_id'             => $user->id,
                    ]);
                }

                // Create an initial wallet top-up (BalanceIn).
                // The issuer sends minor units (5000 = Rs 50.00); the
                // legacy ledger stores major units — convert on the way in.
                if ($initialBalance > 0) {
                    app(\App\Services\LedgerService::class)->credit([
                        'user_id'      => $user->id,
                        'card_id'      => $card->id,
                        'amount'       => $initialBalance / 100,
                        'type'         => 'topup',
                        'remarks'      => 'Initial top-up at card registration',
                    ]);
                }

                Log::info('Card registered via bridge', [
                    'card_uid'    => $validated['card_uid'],
                    'card_number' => $card->card_number,
                    'user_id'     => $user->id,
                    'balance'     => $initialBalance,
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'card_id'     => $card->id,
                        'card_number' => $card->card_number,
                        'card_uid'    => $validated['card_uid'],
                        'user_id'     => $user->id,
                        'status'      => CardStatus::ACTIVE->value,
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
