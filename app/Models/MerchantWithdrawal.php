<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantWithdrawal extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'amount',
        'status',
        'transaction_id',
        'gateway_name',
        'remarks',
    ];

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }
}
