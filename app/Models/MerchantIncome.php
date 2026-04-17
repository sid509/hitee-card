<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantIncome extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'balance_out_id',
        'reference_id',
        'reference_type',
        'amount',
        'type',
    ];

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function reference()
    {
        return $this->morphTo();
    }

    public function transaction()
    {
        return $this->belongsTo(BalanceOut::class, 'balance_out_id');
    }
}
