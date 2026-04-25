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
            return [
                'position' => $banner->position,
                'type'     => $banner->type,
                'items'    => $banner->items,
            ];
        });

        return apiResponse(true, 'Banners fetched successfully', $banners);
    }
}
