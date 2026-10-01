<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CardInitializationOperation extends Model
{
    use HasUuids;

    protected $table = 'card_initialization_operations';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'card_id',
        'idempotency_key',
        'expected_uid',
        'status',
        'current_step',
        'last_successful_step',
        'last_attempted_step',
        'physical_state_uncertain',
        'lock_version',
        'workstation_id',
        'card_structure_version',
        'key_profile_version',
        'key_service_request_id',
        'key_manifest',
        'failure_code',
        'failure_message',
        'operation_mode',
        'write_mode',
        'authorization_key_profile_version',
        'physical_write_authorized_at',
        'operator_confirmation_sha256',
        'profile_snapshot',
        'started_at',
        'completed_at',
        'failed_at',
    ];

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
