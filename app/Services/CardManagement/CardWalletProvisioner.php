<?php

declare(strict_types=1);

namespace App\Services\CardManagement;

use App\Enums\CardStatus;
use App\Models\Card;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Links a registered card to a wallet owner so it can be tapped.
 *
 * Extracted from CardRegistrationBridgeController (ADR 0018): the same
 * find-or-link is now used internally by the issuance-complete flow, so a
 * card issued through the Laravel Card Management API becomes tap-able
 * without the legacy Node sync. The bridge endpoint delegates here too.
 *
 * Ensures the card has:
 *   - a User (wallet owner),
 *   - hitee_card_number set (the on-card number verified at tap time),
 *   - ACTIVE status + is_currently_active/is_physical flags,
 *   - an opening BalanceIn ledger entry when a balance is configured.
 */
final class CardWalletProvisioner
{
    /**
     * @return array{card_id: mixed, card_number: string, card_uid: string,
     *               user_id: int, status: string, already_registered: bool}
     */
    public function provision(
        string $cardUid,
        ?string $cardNumber = null,
        ?int $initialBalanceMinor = null,
        ?string $operatorName = null,
    ): array {
        $initialBalanceMinor ??= (int) config('card_management.wallet.opening_balance_minor_units', 5000);

        return DB::transaction(function () use ($cardUid, $cardNumber, $initialBalanceMinor, $operatorName) {
            $card = Card::where('card_uid', $cardUid)->lockForUpdate()->first();

            if ($card && $card->user_id) {
                // Wallet already linked — idempotent replay. Ensure the card
                // is still ACTIVE (e.g. a re-issuance completed later).
                if ($card->status === CardStatus::ISSUED->value) {
                    $card->update([
                        'status' => CardStatus::ACTIVE->value,
                        'is_currently_active' => true,
                    ]);
                }

                return $this->result($card, true);
            }

            $user = User::create([
                'name' => $operatorName ?: 'Card Holder',
                'email' => 'card_'.Str::lower($cardUid).'@hitee.cards',
                'password' => bcrypt(Str::random(32)),
                'role' => 'passenger',
            ]);

            if ($card) {
                // Card exists in the registry (CM lifecycle) — link the
                // wallet owner and activate for taps.
                $card->update([
                    'user_id' => $user->id,
                    'hwid' => $card->hwid ?? 'HW-'.strtoupper(Str::random(10)),
                    'hitee_card_number' => $card->hitee_card_number ?? ($cardNumber ?: $card->card_number),
                    'status' => CardStatus::ACTIVE->value,
                    'is_currently_active' => true,
                    'is_physical' => true,
                ]);
            } else {
                // Card unknown to the registry (external/legacy path) —
                // create it so taps can resolve.
                $externalNumber = $cardNumber ?? '';
                $registryNumber = str_starts_with($externalNumber, 'CRD-')
                    ? $externalNumber
                    : 'CRD-'.strtoupper(Str::random(10));

                $card = Card::create([
                    'card_number' => $registryNumber,
                    'hwid' => 'HW-'.strtoupper(Str::random(10)),
                    'card_uid' => $cardUid,
                    'hitee_card_number' => $externalNumber,
                    'card_type_code' => '0100',
                    'card_type_label' => 'STANDARD',
                    'status' => CardStatus::ACTIVE->value,
                    'is_currently_active' => true,
                    'is_physical' => true,
                    'is_personalized' => false,
                    'user_id' => $user->id,
                ]);
            }

            // Minor units (paisa) → major units for the legacy ledger.
            if ($initialBalanceMinor > 0) {
                app(LedgerService::class)->credit([
                    'user_id' => $user->id,
                    'card_id' => $card->id,
                    'amount' => $initialBalanceMinor / 100,
                    'type' => 'topup',
                    'remarks' => 'Initial top-up at card issuance',
                ]);
            }

            Log::info('Card wallet provisioned', [
                'card_uid' => $card->card_uid,
                'card_id' => $card->id,
                'user_id' => $user->id,
                'balance' => $initialBalanceMinor,
            ]);

            return $this->result($card->fresh(), false);
        });
    }

    private function result(Card $card, bool $already): array
    {
        return [
            'card_id' => $card->id,
            'card_number' => $card->card_number,
            'card_uid' => $card->card_uid,
            'user_id' => $card->user_id,
            'status' => $card->status,
            'already_registered' => $already,
        ];
    }
}
