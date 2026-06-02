<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserNotification extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'user_id',
        'broadcast_id',
        'template_id',
        'type',
        'subject',
        'body',
        'language',
        'status',
        'seen_at',
        'error_message',
    ];

    protected $casts = [
        'seen_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function broadcast()
    {
        return $this->belongsTo(Broadcast::class);
    }

    public function template()
    {
        return $this->belongsTo(NotificationTemplate::class);
    }
}
