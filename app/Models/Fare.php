<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class Fare extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['merchant_id', 'route_id', 'name', 'status', 'effective_from'];

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function matrices()
    {
        return $this->hasMany(FareMatrix::class);
    }

    public function buses()
    {
        return $this->hasMany(Bus::class, 'active_fare_id');
    }
}
