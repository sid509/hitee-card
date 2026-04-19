<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tap extends Model
{
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
