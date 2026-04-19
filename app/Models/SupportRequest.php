<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class SupportRequest extends Model
{
    use SoftDeletes;
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
