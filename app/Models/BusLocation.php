<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusLocation extends Model
{
    protected $fillable = [
        'bus_id',
        'latitude',
        'longitude',
        'heading',
        'speed',
        'recorded_at'
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
    ];

    public function bus()
    {
        return $this->belongsTo(Bus::class);
    }
}
