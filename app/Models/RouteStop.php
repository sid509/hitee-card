<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RouteStop extends Model
{
    use HasFactory;

    protected $fillable = ['route_id', 'stop_name', 'latitude', 'longitude', 'order'];

    public function route()
    {
        return $this->belongsTo(Route::class);
    }
}
