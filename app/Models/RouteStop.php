<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class RouteStop extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['route_id', 'stop_name', 'latitude', 'longitude', 'order'];

    public function route()
    {
        return $this->belongsTo(Route::class);
    }
}
