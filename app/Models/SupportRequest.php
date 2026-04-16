<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportRequest extends Model
{
    protected $fillable = [
        'user_id',
        'subject',
        'message',
        'status',
        'closing_reason',
        'closed_at'
    ];

    protected $casts = [
        'closed_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
