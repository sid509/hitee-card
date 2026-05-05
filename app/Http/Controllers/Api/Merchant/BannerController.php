<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;

/**
 * @group MerchantApi 
 * @subgroup Banner
 */
class BannerController extends Controller
{
    /**
     * Get Merchant Banners
     * 
     * @queryParam position string (optional) Filter by position (merchant_home_top, merchant_home_middle).
     */
    public function index(Request $request)
    {
        $request->validate([
            'position' => 'nullable|string|in:merchant_home_top,merchant_home_middle',
        ]);

        $query = Banner::query()->active();

        if ($request->filled('position')) {
            $query->where('position', $request->position);
        } else {
            // Default to merchant positions if none specified
            $query->whereIn('position', ['merchant_home_top', 'merchant_home_middle']);
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
