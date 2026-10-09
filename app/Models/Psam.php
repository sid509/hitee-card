<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Psam extends Model
{
    protected $table = 'cm_psams';
    public $incrementing = false;
    protected $keyType = 'string';

    public const STATUS_INITIALIZED = 'INITIALIZED';
    public const STATUS_INSTALLED = 'INSTALLED';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_SUSPENDED = 'SUSPENDED';
    public const STATUS_RETIRED = 'RETIRED';

    protected $fillable = [
        'psam_number', 'atr', 'adf_aid', 'status', 'key_profile_id',
        'root_key_versions', 'issuer_workstation_id', 'issuer_operator_id',
        'issued_at', 'installed_device_id', 'installed_at', 'metadata',
    ];

    protected $casts = [
        'root_key_versions' => 'array',
        'metadata' => 'array',
        'issued_at' => 'datetime',
        'installed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = Str::uuid()->toString();
            }
        });
    }

    public function issuanceOperations(): HasMany
    {
        return $this->hasMany(PsamIssuanceOperation::class, 'psam_id');
    }
}
