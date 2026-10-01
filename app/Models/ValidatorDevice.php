<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ValidatorDevice extends Model
{
    protected $table = 'validator_devices';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'device_id', 'vehicle_id', 'route_id', 'terminal_number_hex',
        'status', 'api_token', 'firmware_version', 'metadata',
        'last_heartbeat_at', 'registered_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_heartbeat_at' => 'datetime',
        'registered_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = Str::uuid()->toString();
            }
            // Store the raw token; it will be hashed in the controller before saving
        });
    }

    public function trips(): HasMany
    {
        return $this->hasMany(ValidatorTrip::class, 'device_id');
    }

    public function blocklistCursor(): HasMany
    {
        return $this->hasMany(BlocklistCursor::class, 'device_id');
    }
}
