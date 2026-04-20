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

    protected $appends = [
        'formatted_created_at',
        'formatted_closed_at'
    ];

    public function getFormattedCreatedAtAttribute()
    {
        return formatDate($this->created_at);
    }

    public function getFormattedClosedAtAttribute()
    {
        return formatDate($this->closed_at);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
