<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class Tap extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'user_id',
        'card_id',
        'merchant_id',
        'reference_id',
        'reference_type',
        'type',
        'stop_id',
        'resolved_location_name',
        'latitude',
        'longitude'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'card_id' => 'integer',
        'merchant_id' => 'integer',
        'reference_id' => 'integer',
        'stop_id' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function card()
    {
        return $this->belongsTo(Card::class);
    }

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
