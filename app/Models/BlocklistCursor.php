<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BlocklistCursor extends Model
{
    protected $table = 'blocklist_cursors';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['device_id', 'last_synced_at', 'last_synced_sequence'];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = Str::uuid()->toString();
            }
        });
    }
}
