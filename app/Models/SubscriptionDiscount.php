<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionDiscount extends Model
{
    protected $table = 'subscription_discounts';

    protected $fillable = [
        'subscription_model_id',
        'service_partner_id',
        'discount_type',
        'discount_value',
        'min_spend',
        'description'
    ];

    public function subscriptionModel()
    {
        return $this->belongsTo(SubscriptionModel::class);
    }

    public function servicePartner()
    {
        return $this->belongsTo(ServicePartner::class);
    }
}
