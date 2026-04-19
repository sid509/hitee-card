<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class ParkingAttribute extends Model
{
    use SoftDeletes;
    protected $fillable = ['name', 'icon'];

    public function parkings()
    {
        return $this->belongsToMany(Parking::class);
    }

    public function getIconUrlAttribute()
    {
        return $this->icon ? asset('storage/' . $this->icon) : null;
    }
}
