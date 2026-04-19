<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

use App\Traits\HasMedia;

class Bus extends Model
{
    use SoftDeletes, HasMedia;
    protected $fillable = ['name', 'bus_number', 'hwid', 'status', 'merchant_id', 'latitude', 'longitude', 'route_id', 'active_fare_id'];

    public function getFeaturedImageUrlAttribute()
    {
        return $this->getFirstMediaUrl('featured', asset('assets/img/no_image.png'));
    }

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function activeFare()
    {
        return $this->belongsTo(Fare::class, 'active_fare_id');
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
