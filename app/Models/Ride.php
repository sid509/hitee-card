<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ride extends Model
{
    protected $fillable = [
        'user_id',
        'card_id',
        'merchant_id',
        'reference_id',
        'reference_type',
        'tap_in_id',
        'tap_out_id',
        'fare_amount',
        'status'
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

    public function tapIn()
    {
        return $this->belongsTo(Tap::class, 'tap_in_id');
    }

    public function tapOut()
    {
        return $this->belongsTo(Tap::class, 'tap_out_id');
    }
}
