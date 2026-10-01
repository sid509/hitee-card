<?php

namespace App\Models\CardManagement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ValidatorTrip extends Model
{
    protected $table = 'cm_validator_trips';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'device_id', 'trip_id', 'card_uid', 'card_type',
        'tap_in_at', 'tap_in_lat', 'tap_in_lng',
        'tap_out_at', 'tap_out_lat', 'tap_out_lng',
        'distance_meters', 'fare_amount', 'fare_minor_units',
        'balance_before', 'balance_after', 'offline_counter',
        'transaction_datetime', 'terminal_transaction_sequence',
        'reconciliation_status', 'sync_status', 'idempotency_key',
        'settlement_batch_id',
    ];

    protected $casts = [
        'tap_in_at' => 'datetime',
        'tap_out_at' => 'datetime',
        'tap_in_lat' => 'decimal:7',
        'tap_in_lng' => 'decimal:7',
        'tap_out_lat' => 'decimal:7',
        'tap_out_lng' => 'decimal:7',
        'distance_meters' => 'decimal:2',
        'fare_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = Str::uuid()->toString();
            }
        });
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(ValidatorDevice::class, 'device_id');
    }
}
