<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PsamIssuanceOperation extends Model
{
    protected $table = 'cm_psam_issuance_operations';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'psam_id', 'psam_number', 'operation_mode', 'result',
        'failed_step', 'error', 'status_word', 'steps', 'atr',
        'workstation_id', 'operator_id', 'issued_at',
    ];

    protected $casts = [
        'steps' => 'array',
        'issued_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = Str::uuid()->toString();
            }
        });
    }

    public function psam(): BelongsTo
    {
        return $this->belongsTo(Psam::class, 'psam_id');
    }
}
