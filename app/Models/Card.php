<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    protected $fillable = ['card_number', 'hwid', 'status', 'is_currently_active', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
