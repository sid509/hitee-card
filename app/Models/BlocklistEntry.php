<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BlocklistEntry extends Model
{
    protected $table = 'blocklist_entries';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'card_uid', 'card_number', 'reason', 'status',
        'effective_at', 'lifted_at', 'source_operation_id', 'source',
    ];

    protected $casts = [
        'effective_at' => 'datetime',
        'lifted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = Str::uuid()->toString();
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }
}
