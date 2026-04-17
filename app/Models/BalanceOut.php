<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BalanceOut extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'merchant_id',
        'amount',
        'type',
        'remarks',
        'reference_id',
        'reference_type',
        'created_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
