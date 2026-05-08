<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasMedia;

class CardApplication extends Model
{
    use HasFactory, SoftDeletes, HasMedia;

    protected $fillable = [
        'user_id',
        'type',
        'status',
        'kyc_data',
        'admin_remarks',
        'processed_at',
        'card_id',
    ];

    protected $casts = [
        'kyc_data' => 'array',
        'processed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function card()
    {
        return $this->belongsTo(Card::class);
    }

    public function getKycDocumentUrlAttribute()
    {
        return $this->getFirstMediaUrl('kyc_document', asset('assets/img/no_image.png'));
    }
}
