<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Broadcast extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'template_id',
        'sent_by',
        'type',
        'title',
        'total_count',
        'success_count',
        'fail_count',
    ];

    public function template()
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function notifications()
    {
        return $this->hasMany(UserNotification::class);
    }
}
