<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class ParkingAttribute extends Model
{
    use SoftDeletes;
    protected $fillable = ['name', 'icon'];

    protected $appends = ['icon_url'];

    public function parkings()
    {
        return $this->belongsToMany(Parking::class);
    }

    public function getIconUrlAttribute()
    {
        return $this->icon ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->icon) : asset('assets/img/no_image.png');
    }
}
