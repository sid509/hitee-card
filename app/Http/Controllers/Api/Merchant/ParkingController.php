<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Parking;
use Illuminate\Http\Request;

/**
 * @group MerchantApi 
 * @subgroup Parking
 */
class ParkingController extends Controller
{
    /**
     * List Merchant Parkings
     */
    public function index(Request $request)
    {
        $merchant = $request->user();

        $query = Parking::where('merchant_id', $merchant->id)
            ->with(['attributes', 'fees'])
            ->withCount('ongoingRides');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('location', 'like', "%{$request->search}%");
            });
        }

        $parkings = $query->get()->map(function($p) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'location' => $p->location,
                'status' => $p->status,
                'total_capacity' => (int) $p->total_capacity,
                'current_occupancy' => (int) $p->ongoing_rides_count,
                'latitude' => $p->latitude,
                'longitude' => $p->longitude,
                'image_url' => $p->featured_image_url,
                'fees' => $p->fees->map(fn($f) => [
                    'title' => $f->title,
                    'subtitle' => $f->subtitle,
                    'price_rs' => (float) $f->price_rs,
                    'price_pts' => (float) $f->price_pts,
                ]),
                'attributes' => $p->attributes->map(fn($a) => [
                    'name' => $a->name,
                    'icon' => $a->icon_url,
                ]),
            ];
        });

        return apiResponse(true, 'Parkings fetched successfully', $parkings);
    }

    /**
     * Show Parking Details
     */
    public function show(Request $request, $id)
    {
        $parking = Parking::with(['attributes', 'fees', 'media'])
            ->withCount('ongoingRides')
            ->find($id);

        if (!$parking) {
            return apiResponse(false, 'Parking not found', null, 404);
        }

        if ($parking->merchant_id !== $request->user()->id) {
            return apiResponse(false, 'You do not have permission to view this parking', null, 403);
        }

        $data = [
            'id' => $parking->id,
            'name' => $parking->name,
            'location' => $parking->location,
            'status' => $parking->status,
            'total_capacity' => (int) $parking->total_capacity,
            'current_occupancy' => (int) $parking->ongoing_rides_count,
            'latitude' => $parking->latitude,
            'longitude' => $parking->longitude,
            'image_url' => $parking->featured_image_url,
            'fees' => $parking->fees->map(fn($f) => [
                'title' => $f->title,
                'subtitle' => $f->subtitle,
                'price_rs' => (float) $f->price_rs,
                'price_pts' => (float) $f->price_pts,
            ]),
            'attributes' => $parking->attributes->map(fn($a) => [
                'name' => $a->name,
                'icon' => $a->icon_url,
            ]),
            'gallery' => $parking->media->where('collection_name', 'gallery')->map(fn($m) => [
                'id' => $m->id,
                'url' => $m->url,
            ])->values(),
        ];

        return apiResponse(true, 'Parking details fetched successfully', $data);
    }
}
