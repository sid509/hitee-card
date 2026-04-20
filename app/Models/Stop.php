<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Stop extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'latitude', 'longitude', 'description'];

    public function routeStops()
    {
        return $this->hasMany(RouteStop::class);
    }

    public function routes()
    {
        return $this->belongsToMany(Route::class, 'route_stops');
    }
}
