<?php

namespace App\Services\CardManagement;

use App\Models\CardManagement\Card;
use App\Models\CardManagement\CardInitializationOperation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CardsService
{
    /**
     * Check if a card UID is registered.
     */
    public function checkCard(string $uid): array
    {
        $this->validateUid($uid);
        $card = Card::where('uid', $uid)->first();

        return $card
            ? ['registered' => true, 'card' => $card->toPublicArray()]
            : ['registered' => false, 'card' => null];
    }

    /**
     * Register a new card.
     */
    public function registerCard(array $input): Card
    {
        $uid = $this->validateUid($input['uid'] ?? '');
        $remarks = $input['remarks'] ?? null;
        $cardTypeCode = $input['cardTypeCode'] ?? config('card_management.card_profile.default_card_type_code');

        if (!preg_match('/^[0-9]{4}$/', $cardTypeCode)) {
            throw new CardManagementError('AMOUNT_INVALID', 400, 'cardTypeCode must be 4 digits.');
        }

        if (Card::where('uid', $uid)->exists()) {
            throw new CardAlreadyRegisteredError();
        }

        return DB::transaction(function () use ($uid, $remarks, $cardTypeCode) {
            $allocation = $this->allocateCardNumber();

            $card = Card::create([
                'id' => Str::uuid()->toString(),
                'uid' => $uid,
                'card_number' => $allocation['cardNumber'],
                'card_number_prefix' => $allocation['prefix'],
                'card_number_sequence' => $allocation['sequence'],
                'card_type_code' => $cardTypeCode,
                'card_type_label' => 'STANDARD',
                'status' => 'REGISTERED',
                'metadata' => ['remarks' => $remarks],
                'card_structure_version' => config('card_management.card_profile.structure_version'),
                'key_profile_version' => config('card_management.card_profile.key_profile_version'),
                'environment' => config('card_management.card_profile.environment'),
                'production_eligible' => config('card_management.card_profile.production_eligible'),
            ]);

            return $card;
        });
    }

    /**
     * Get a card by UID.
     */
    public function getCard(string $uid): Card
    {
        $this->validateUid($uid);
        $card = Card::where('uid', $uid)->first();
        if (!$card) {
            throw new CardNotRegisteredError();
        }
        return $card;
    }

    /**
     * Assign the active lab profile to a card.
     */
    public function assignLabProfile(string $uid): Card
    {
        $card = $this->getCard($uid);
        $card->update([
            'key_profile_version' => config('card_management.card_profile.key_profile_version'),
        ]);
        return $card->fresh();
    }

    /**
     * Get the current/active initialization operation for a card.
     */
    public function getInitializationOperation(string $uid): array
    {
        $card = $this->getCard($uid);
        $operation = CardInitializationOperation::where('card_id', $card->id)
            ->whereIn('status', ['KEYS_PENDING', 'KEYS_PREPARED', 'IN_PROGRESS', 'VERIFYING'])
            ->orderBy('created_at', 'desc')
            ->first();

        return [
            'card' => $card->toPublicArray(),
            'operation' => $operation?->toPublicArray(),
        ];
    }

    /**
     * Create an initialization operation.
     */
    public function createInitializationOperation(string $uid, array $input): CardInitializationOperation
    {
        $card = $this->getCard($uid);
        $mode = $input['mode'] ?? 'INITIALIZE';

        if (!in_array($mode, ['INITIALIZE', 'REINITIALIZE'], true)) {
            throw new CardManagementError('AMOUNT_INVALID', 400, 'mode must be INITIALIZE or REINITIALIZE.');
        }

        if ($mode === 'INITIALIZE' && $card->status === 'INITIALIZED') {
            throw new CardAlreadyInitializedError();
        }

        $active = CardInitializationOperation::where('card_id', $card->id)
            ->whereIn('status', ['KEYS_PENDING', 'KEYS_PREPARED', 'IN_PROGRESS', 'VERIFYING'])
            ->exists();
        if ($active) {
            throw new ActiveOperationExistsError();
        }

        $operation = CardInitializationOperation::create([
            'id' => Str::uuid()->toString(),
            'card_id' => $card->id,
            'idempotency_key' => request()->attributes->get('cm_idempotency_key') ?? Str::uuid()->toString(),
            'expected_uid' => $uid,
            'status' => 'KEYS_PENDING',
            'current_step' => 'KEYS_PENDING',
            'lock_version' => 1,
            'workstation_id' => request()->attributes->get('cm_workstation_id'),
            'card_structure_version' => $card->card_structure_version,
            'key_profile_version' => $card->key_profile_version,
            'operation_mode' => $mode,
            'write_mode' => $card->environment,
            'profile_snapshot' => [
                'profileId' => config('card_management.card_profile.key_profile_version'),
                'cardCapacityKb' => config('card_management.card_profile.lab_profile.card_capacity_kb'),
                'ef0017Required' => config('card_management.card_profile.lab_profile.ef0017_required'),
                'cityCode' => config('card_management.card_profile.lab_profile.city_code'),
                'issuerCode' => config('card_management.card_profile.lab_profile.issuer_code'),
                'applicationIdentifier' => config('card_management.card_profile.lab_profile.application_identifier'),
                'cardDataVersion' => config('card_management.card_profile.lab_profile.card_data_version'),
                'cardEnableFlag' => config('card_management.card_profile.lab_profile.card_enable_flag'),
                'applicationVersion' => config('card_management.card_profile.lab_profile.application_version'),
                'applicationType' => config('card_management.card_profile.lab_profile.application_type'),
                'applicationEnableFlag' => config('card_management.card_profile.lab_profile.application_enable_flag'),
                'depositMinorUnits' => config('card_management.card_profile.lab_profile.deposit_minor_units'),
                'expiryYears' => config('card_management.card_profile.lab_profile.expiry_years'),
                'diversificationProfile' => config('card_management.card_profile.lab_profile.diversification_profile_version'),
                'diversificationMapping' => config('card_management.card_profile.lab_profile.diversification_mapping'),
                'perKeyDiversificationChains' => config('card_management.card_profile.lab_profile.per_key_diversification_chains'),
            ],
        ]);

        return $operation;
    }

    private function allocateCardNumber(): array
    {
        $prefix = config('card_management.card_profile.card_number_prefix');
        $suffixDigits = 16 - strlen($prefix);

        return DB::transaction(function () use ($prefix, $suffixDigits) {
            $row = DB::table('cm_card_number_allocations')
                ->where('prefix', $prefix)
                ->lockForUpdate()
                ->first();

            if ($row) {
                $next = $row->last_sequence + 1;
                DB::table('cm_card_number_allocations')
                    ->where('prefix', $prefix)
                    ->update(['last_sequence' => $next, 'updated_at' => now()]);
            } else {
                $next = 1;
                DB::table('cm_card_number_allocations')->insert([
                    'prefix' => $prefix,
                    'last_sequence' => $next,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return [
                'prefix' => $prefix,
                'sequence' => $next,
                'cardNumber' => $prefix . str_pad((string)$next, $suffixDigits, '0', STR_PAD_LEFT),
            ];
        });
    }

    private function validateUid(string $uid): string
    {
        $normalized = strtoupper($uid);
        if (!preg_match('/^[0-9A-F]{8,32}$/', $normalized) || strlen($normalized) % 2 !== 0) {
            throw new CardManagementError('AMOUNT_INVALID', 400, 'uid must be 8-32 hex characters.');
        }
        return $normalized;
    }
}
