<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    /**
     * GET /api/banners
     *
     * Returns a list of active banners.
     *
     * @queryParam position string (optional) Filter banners by position (e.g., home_top, wallet_top).
     */
    public function index(Request $request)
    {
        $request->validate([
            'position' => 'nullable|string|in:home_top,home_middle,home_bottom,wallet_top',
        ]);

        $query = Banner::query()->active();

        // Filter by position if provided
        if ($request->filled('position')) {
            $query->where('position', $request->position);
        }

        $banners = $query->get()->map(function($banner) {
            $items = [];

            if ($banner->type === 'single') {
                $items[] = [
                    'title'     => $banner->title,
                    'image_url' => $banner->image_path ? asset('storage/' . $banner->image_path) : null,
                    'link'      => $banner->link,
                ];
            } elseif ($banner->type === 'carousel' && is_array($banner->images)) {
                foreach ($banner->images as $item) {
                    $items[] = [
                        'title'     => $item['title'] ?? null,
                        'image_url' => isset($item['image_path']) ? asset('storage/' . $item['image_path']) : null,
                        'link'      => $item['link'] ?? null,
                    ];
                }
            }

            return [
                'position' => $banner->position,
                'type'     => $banner->type, // 'single' | 'carousel'
                'items'    => $items,
            ];
        });

        return apiResponse(true, 'Banners fetched successfully', $banners);
    }
}
