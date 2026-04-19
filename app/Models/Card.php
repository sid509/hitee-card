<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class Card extends Model
{
    use SoftDeletes;
    protected $fillable = ['card_number', 'hwid', 'status', 'is_currently_active', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function hasOngoingRide()
    {
        return Ride::where('card_id', $this->id)->where('status', 'ongoing')->exists();
    }
}
