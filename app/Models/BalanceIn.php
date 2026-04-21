<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class BalanceIn extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'card_id',
        'amount',
        'type',
        'remarks',
        'created_by',
        'gateway_name',
        'transaction_id',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function card()
    {
        return $this->belongsTo(Card::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
