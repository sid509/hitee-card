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

    public function balanceIns()
    {
        return $this->hasMany(BalanceIn::class);
    }

    public function balanceOuts()
    {
        return $this->hasMany(BalanceOut::class);
    }

    public function balance()
    {
        $in = $this->balanceIns()->where('status', 'completed')->sum('amount');
        $out = $this->balanceOuts()->sum('amount');
        return $in - $out;
    }

    public function taps()
    {
        return $this->hasMany(Tap::class);
    }

    public function rides()
    {
        return $this->hasMany(Ride::class);
    }

    public function hasOngoingRide()
    {
        return Ride::where('card_id', $this->id)->where('status', 'ongoing')->exists();
    }
}
