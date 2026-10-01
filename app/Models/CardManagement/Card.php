<?php

namespace App\Models\CardManagement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Card extends Model
{
    use HasUuids;

    protected $table = 'cm_cards';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'production_eligible' => 'boolean',
        'initialized_at' => 'datetime',
        'issued_at' => 'datetime',
        'activated_at' => 'datetime',
        'blocked_at' => 'datetime',
        'last_card_verification_at' => 'datetime',
    ];

    public function toPublicArray(): array
    {
        $metadata = $this->metadata ?? [];
        return [
            'id' => $this->id,
            'uid' => $this->uid,
            'cardNumber' => $this->card_number,
            'cardTypeCode' => $this->card_type_code,
            'cardTypeLabel' => $this->card_type_label,
            'status' => $this->status,
            'cardStructureVersion' => $this->card_structure_version,
            'keyProfileVersion' => $this->key_profile_version,
            'environment' => $this->environment,
            'productionEligible' => $this->production_eligible,
            'installedKeyProfileVersion' => $this->installed_key_profile_version,
            'installedCardStructureVersion' => $this->installed_card_structure_version,
            'lastCardVerificationAt' => $this->last_card_verification_at?->toIso8601ZuluString(),
            'customerId' => $this->customer_id,
            'issuedAt' => $this->issued_at?->toIso8601ZuluString(),
            'activatedAt' => $this->activated_at?->toIso8601ZuluString(),
            'blockedAt' => $this->blocked_at?->toIso8601ZuluString(),
            'lifecycleReason' => $this->lifecycle_reason,
            'currentIssuanceOperationId' => $this->current_issuance_operation_id,
            'currentReplacementOperationId' => $this->current_replacement_operation_id,
            'replacesCardId' => $this->replaces_card_id,
            'replacedByCardId' => $this->replaced_by_card_id,
            'remarks' => $metadata['remarks'] ?? null,
            'initializedAt' => $this->initialized_at?->toIso8601ZuluString(),
            'registeredAt' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
