<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;

class BannerController extends Controller
{
    /**
     * Display a listing of the banners (fixed positions).
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = Banner::query();
            return DataTables::of($data)
                ->addIndexColumn()
                ->editColumn('position', function($row){
                    return '<span class="badge bg-label-primary">'.strtoupper(str_replace('_', ' ', $row->position)).'</span>';
                })
                ->addColumn('image', function($row){
                    if ($row->type === 'carousel') {
                        $count = is_array($row->images) ? count($row->images) : 0;
                        return '<div class="preview-trigger cursor-pointer" data-position="'.$row->position.'" data-type="carousel">
                                    <span class="badge bg-label-info"><i class="bx bx-images me-1"></i> '.$count.' Images</span>
                                </div>';
                    }
                    
                    if (!$row->image_path) return '<span class="text-muted italic">No image</span>';
                    
                    return '<div class="preview-trigger cursor-pointer" data-position="'.$row->position.'" data-type="single">
                                <img src="'.$row->image_url.'" class="rounded" style="height: 50px; width: 100px; object-fit: cover;">
                            </div>';
                })
                ->editColumn('is_active', function($row){
                    $isActive = $row->is_active;
                    $btnClass = $isActive ? 'btn-success' : 'btn-secondary';
                    $btnIcon = $isActive ? 'bx-check-circle' : 'bx-x-circle';
                    $btnTitle = $isActive ? 'Deactivate Banner' : 'Activate Banner';

                    return '<button type="button" class="btn btn-icon btn-sm '.$btnClass.' toggle-banner-status" data-position="'.$row->position.'" title="'.$btnTitle.'"><i class="bx '.$btnIcon.'"></i></button>';
                })
                ->addColumn('action', function($row){
                    return '<button type="button" class="btn btn-sm btn-icon btn-primary edit-banner-btn me-1" 
                                data-position="'.$row->position.'" 
                                data-type="'.$row->type.'" 
                                data-title="'.$row->title.'" 
                                data-link="'.$row->link.'" 
                                data-active="'.$row->is_active.'" 
                                data-image="'.$row->image_url.'" 
                                data-items=\''.json_encode($row->items).'\'>
                                <i class="bx bx-edit-alt"></i>
                            </button>';
                })
                ->rawColumns(['action', 'position', 'image', 'is_active'])
                ->make(true);
        }

        return view('modules.banners.index');
    }

    /**
     * Toggle banner status.
     */
    public function toggleStatus(string $position)
    {
        $banner = Banner::where('position', $position)->firstOrFail();
        $newStatus = !$banner->is_active;
        $banner->update(['is_active' => $newStatus]);

        $statusText = $newStatus ? 'activated' : 'deactivated';

        return response()->json([
            'status' => true,
            'message' => "Banner '{$position}' has been {$statusText}."
        ]);
    }

    /**
     * Update the specified banner.
     */
    public function update(Request $request, string $position)
    {
        $banner = Banner::where('position', $position)->firstOrFail();

        $request->validate([
            'type'   => 'required|in:single,carousel',
            'is_active' => 'nullable',
            // Validation for Single
            'title'  => 'required_if:type,single|nullable|string|max:255',
            'link'   => 'required_if:type,single|nullable|url|max:500',
            'image'  => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            // Validation for Carousel
            'items'  => 'required_if:type,carousel|array|min:1',
            'items.*.title' => 'required_with:items|string|max:255',
            'items.*.link'  => 'required_with:items|url|max:500',
            'items.*.image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $data = [
            'type'      => $request->type,
            'is_active' => $request->has('is_active'),
        ];

        if ($request->type === 'single') {
            $data['title'] = $request->title;
            $data['link']  = $request->link;
            $data['images'] = null; // Clear carousel items

            if ($request->hasFile('image')) {
                if ($banner->image_path) {
                    Storage::disk('public')->delete($banner->image_path);
                }
                $data['image_path'] = $request->file('image')->store("banners/{$position}", 'public');
            }
        } else {
            // Carousel logic
            $data['title'] = $request->title ?? $banner->title; // Main title optional for carousel
            $data['image_path'] = null; // Clear single image
            $data['link'] = null;

            $carouselItems = [];
            $existingItems = $banner->images ?? [];

            foreach ($request->input('items', []) as $index => $itemData) {
                $item = [
                    'title' => $itemData['title'],
                    'link'  => $itemData['link'],
                ];

                // Handle file upload for each carousel item
                if ($request->hasFile("items.{$index}.image")) {
                    $path = $request->file("items.{$index}.image")->store("banners/{$position}/carousel", 'public');
                    $item['image_path'] = $path;
                } elseif (isset($existingItems[$index]['image_path'])) {
                    $item['image_path'] = $existingItems[$index]['image_path'];
                } else {
                    return response()->json(['status' => false, 'message' => "Image required for item {$index}"], 422);
                }

                $carouselItems[] = $item;
            }
            $data['images'] = $carouselItems;
        }

        $banner->update($data);

        return response()->json([
            'status' => true,
            'message' => 'Banner updated successfully.'
        ]);
    }
}
