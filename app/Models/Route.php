<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Route extends Model
{
    use HasFactory;

    protected $fillable = ['merchant_id', 'name', 'description'];

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function stops()
    {
        return $this->hasMany(RouteStop::class)->orderBy('order');
    }

    public function fares()
    {
        return $this->hasMany(Fare::class);
    }

    public function buses()
    {
        return $this->hasMany(Bus::class);
    }
}
