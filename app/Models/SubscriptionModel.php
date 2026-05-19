<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionModel extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'category', 'price', 'is_active'];

    public function cards()
    {
        return $this->belongsToMany(Card::class, 'card_subscription_model');
    }

    public function discounts()
    {
        return $this->hasMany(SubscriptionDiscount::class, 'subscription_model_id');
    }
}
