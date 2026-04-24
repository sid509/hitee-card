<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Banner extends Model
{
    use SoftDeletes;

    /**
     * Fixed banner positions. These are seeded once and can only be updated — never created freely.
     */
    const POSITIONS = [
        'home_top',
        'home_middle',
        'home_bottom',
        'wallet_top',
    ];

    protected $fillable = [
        'position',
        'type',
        'title',
        'image_path',
        'images',
        'link',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'images' => 'array',
    ];

    /**
     * Scope: active banners only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
