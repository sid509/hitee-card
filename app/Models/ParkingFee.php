<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ParkingFee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'parking_id',
        'title',
        'subtitle',
        'price_rs',
        'price_pts',
        'order'
    ];

    public function parking()
    {
        return $this->belongsTo(Parking::class);
    }
}
