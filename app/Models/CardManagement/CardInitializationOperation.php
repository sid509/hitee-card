<?php

namespace App\Models\CardManagement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CardInitializationOperation extends Model
{
    use HasUuids;

    protected $table = 'cm_card_initialization_operations';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'key_manifest' => 'array',
        'profile_snapshot' => 'array',
        'physical_state_uncertain' => 'boolean',
        'physical_write_authorized_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'cardId' => $this->card_id,
            'expectedUid' => $this->expected_uid,
            'status' => $this->status,
            'version' => $this->lock_version,
            'mode' => $this->operation_mode,
            'keyProfileVersion' => $this->key_profile_version,
            'keyManifest' => $this->key_manifest,
            'profileSnapshot' => $this->profile_snapshot,
            'lastSuccessfulStep' => $this->last_successful_step,
            'failureCode' => $this->failure_code,
            'failureMessage' => $this->failure_message,
            'physicalStateUncertain' => $this->physical_state_uncertain,
            'createdAt' => $this->created_at?->toIso8601ZuluString(),
            'updatedAt' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
