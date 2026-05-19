<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServicePartner extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'merchant_id',
        'name',
        'service_type',
        'address',
        'latitude',
        'longitude',
        'status'
    ];

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function discounts()
    {
        return $this->hasMany(SubscriptionDiscount::class);
    }
}
