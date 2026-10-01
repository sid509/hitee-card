<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class Card extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'cards';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'production_eligible' => 'boolean',
        'is_currently_active' => 'boolean',
        'is_physical' => 'boolean',
        'is_personalized' => 'boolean',
        'initialized_at' => 'datetime',
        'issued_at' => 'datetime',
        'activated_at' => 'datetime',
        'blocked_at' => 'datetime',
        'last_card_verification_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subscriptionModels()
    {
        return $this->belongsToMany(SubscriptionModel::class, 'card_subscription_model');
    }

    public function balanceIns()
    {
        return $this->hasMany(BalanceIn::class);
    }

    public function balanceOuts()
    {
        return $this->hasMany(BalanceOut::class);
    }

    public function balance()
    {
        $in = $this->balanceIns()->where('status', 'completed')->sum('amount');
        $out = $this->balanceOuts()->sum('amount');
        return $in - $out;
    }

    public function taps()
    {
        return $this->hasMany(Tap::class);
    }

    public function rides()
    {
        return $this->hasMany(Ride::class);
    }

    public function hasOngoingRide()
    {
        return Ride::where('card_id', $this->id)->where('status', 'ongoing')->exists();
    }

    public function toPublicArray(): array
    {
        $metadata = $this->metadata ?? [];
        return [
            'id' => $this->id,
            'uid' => $this->card_uid,
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
