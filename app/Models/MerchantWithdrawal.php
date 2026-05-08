<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class MerchantWithdrawal extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'merchant_id',
        'amount',
        'status',
        'transaction_id',
        'gateway_name',
        'remarks',
        'payload',
    ];

    protected $casts = [
        'merchant_id' => 'integer',
        'amount' => 'float',
        'payload' => 'array',
    ];

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }
}
