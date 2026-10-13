<?php

declare(strict_types=1);

namespace App\Http\Controllers\CardManagement;

use App\Http\Controllers\Controller;
use App\Services\CardManagement\CardWalletProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Card registration bridge endpoint.
 *
 * Legacy adapter for the Node.js card-management-api platform sync — it now
 * delegates to CardWalletProvisioner, the same service the Laravel issuance
 * flow uses internally (ADR 0018). New integrations should let issuance
 * complete provision the wallet instead of calling this endpoint.
 *
 * Ensures the card has:
 *   - A User (wallet owner).
 *   - An initial BalanceIn ledger entry (opening top-up).
 */
class CardRegistrationBridgeController extends Controller
{
    public function registerCard(Request $request, CardWalletProvisioner $provisioner): JsonResponse
    {
        $validated = $request->validate([
            'card_uid' => 'required|string|size:8',
            'card_number' => 'required|string|max:32',
            'card_type_code' => 'nullable|string|max:10',
            'card_type_label' => 'nullable|string|max:32',
            'initial_balance' => 'nullable|integer|min:0',
            'operator_name' => 'nullable|string|max:120',
        ]);

        try {
            $result = $provisioner->provision(
                $validated['card_uid'],
                $validated['card_number'],
                $validated['initial_balance'] ?? null,
                $validated['operator_name'] ?? null,
            );

            return response()->json([
                'success' => true,
                'data' => $result + [
                    'balance' => $validated['initial_balance'] ?? (int) config('card_management.wallet.opening_balance_minor_units', 5000),
                ],
            ], $result['already_registered'] ? 200 : 201);
        } catch (\Exception $e) {
            Log::error('Card registration bridge failed', [
                'card_uid' => $validated['card_uid'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'REGISTRATION_FAILED',
                    'message' => 'Failed to register card on platform.',
                ],
            ], 500);
        }
    }
}
