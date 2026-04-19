<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $fillable = [
        'file_path',
        'file_name',
        'collection_name',
        'mime_type',
        'size',
        'custom_properties',
    ];

    protected $casts = [
        'custom_properties' => 'array',
    ];

    public function mediable()
    {
        return $this->morphTo();
    }

    public function getUrlAttribute()
    {
        return Storage::url($this->file_path);
    }
}
