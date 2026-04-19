<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class FareMatrix extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['fare_id', 'from_stop_id', 'to_stop_id', 'amount'];

    public function fare()
    {
        return $this->belongsTo(Fare::class);
    }

    public function fromStop()
    {
        return $this->belongsTo(RouteStop::class, 'from_stop_id');
    }

    public function toStop()
    {
        return $this->belongsTo(RouteStop::class, 'to_stop_id');
    }
}
