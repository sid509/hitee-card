<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parking extends Model
{
    protected $fillable = ['name', 'location', 'status', 'merchant_id'];

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }
}
