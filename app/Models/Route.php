<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class Route extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'description', 'direction'];

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
