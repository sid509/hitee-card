<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bus extends Model
{
    protected $fillable = ['name', 'bus_number', 'hwid', 'status', 'merchant_id', 'latitude', 'longitude'];

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function merchantIncomes()
    {
        return $this->morphMany(MerchantIncome::class, 'reference');
    }

    public function totalIncome()
    {
        return $this->merchantIncomes()->sum('amount');
    }
}
