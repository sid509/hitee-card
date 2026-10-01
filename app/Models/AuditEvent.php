<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AuditEvent extends Model
{
    use HasUuids;

    protected $table = 'audit_events';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'request_id',
        'action',
        'entity_type',
        'entity_id',
        'operation_type',
        'operation_id',
        'card_id',
        'card_number',
        'card_uid',
        'workstation_id',
        'operator_id',
        'http_method',
        'http_path',
        'result',
        'operation_state',
        'recorded_at',
    ];

    protected $casts = [
        'operation_state' => 'array',
        'recorded_at' => 'datetime',
    ];
}
