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

    protected $appends = [
        'image_url',
        'items',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'images' => 'array',
    ];

    public function getImageUrlAttribute()
    {
        return $this->image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->image_path) : asset('assets/img/no_image.png');
    }

    public function getItemsAttribute()
    {
        $items = [];

        if ($this->type === 'single') {
            $items[] = [
                'title'     => $this->title,
                'image_url' => $this->image_url,
                'link'      => $this->link,
            ];
        } elseif ($this->type === 'carousel' && is_array($this->images)) {
            foreach ($this->images as $item) {
                $items[] = [
                    'title'     => $item['title'] ?? null,
                    'image_url' => isset($item['image_path']) ? \Illuminate\Support\Facades\Storage::disk('public')->url($item['image_path']) : asset('assets/img/no_image.png'),
                    'link'      => $item['link'] ?? null,
                ];
            }
        }

        return $items;
    }

    /**
     * Scope: active banners only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
