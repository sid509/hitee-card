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
        $user = $request->user();

        // Get parkings owned by this merchant OR where this user is assigned staff
        // OR parkings belonging to merchants where this user is staff
        $query = Parking::query()
            ->with(['attributes', 'fees'])
            ->withCount('ongoingRides');

        $query->where(function($q) use ($user) {
            $q->where('merchant_id', $user->id)
              ->orWhereHas('assignedStaff', function($sq) use ($user) {
                  $sq->where('user_id', $user->id);
              })
              ->orWhereIn('merchant_id', $user->merchants->pluck('id'));
        });

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
        $user = $request->user();
        $parking = Parking::with(['attributes', 'fees', 'media'])
            ->withCount('ongoingRides')
            ->find($id);

        if (!$parking) {
            return apiResponse(false, 'Parking not found', null, 404);
        }

        $isOwner = $parking->merchant_id === $user->id;
        $isMerchantStaff = $user->merchants()->where('merchant_id', $parking->merchant_id)->exists();
        $isAssignedStaff = $parking->assignedStaff()->where('user_id', $user->id)->exists();

        if (!$isOwner && !$isMerchantStaff && !$isAssignedStaff) {
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
