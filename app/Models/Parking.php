<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class Parking extends Model
{
    use SoftDeletes;
    protected $fillable = ['name', 'location', 'status', 'merchant_id', 'latitude', 'longitude', 'first_hour_fee', 'onwards_hour_fee'];

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function attributes()
    {
        return $this->belongsToMany(ParkingAttribute::class);
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
