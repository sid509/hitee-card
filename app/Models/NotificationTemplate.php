<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationTemplate extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'type',
        'name',
        'subject_en',
        'subject_ne',
        'body_en',
        'body_ne',
        'variables',
    ];

    protected $casts = [
        'variables' => 'array',
    ];
}
