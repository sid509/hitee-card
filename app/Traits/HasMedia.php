<?php

namespace App\Traits;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

trait HasMedia
{
    public function media()
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function addMedia($file, $collection = 'default', $customName = null)
    {
        $fileName = $customName ?? $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension() ?: 'png';
        
        // Ensure filename has extension
        if (!str_contains($fileName, '.')) {
            $fileName .= '.' . $extension;
        }

        $folder = 'media/' . $collection;
        $path = $folder . '/' . uniqid() . '_' . $fileName;

        // Process Image with Intervention
        $manager = new ImageManager(new Driver());
        $image = $manager->read($file);

        // Define dimensions based on collection
        if ($collection === 'avatar') {
            $image->cover(500, 500); // Square crop for profile
        } elseif ($collection === 'featured') {
            $image->cover(800, 600); // 4:3 crop for featured
        } else {
            // Just resize to max width while maintaining aspect ratio for gallery
            $image->scale(width: 1200);
        }

        $encoded = $image->toPng();
        
        Storage::disk('public')->put($path, $encoded);

        return $this->media()->create([
            'file_path' => $path,
            'file_name' => $fileName,
            'collection_name' => $collection,
            'mime_type' => 'image/png',
            'size' => strlen($encoded),
        ]);
    }

    public function getFirstMediaUrl($collection = 'default', $default = null)
    {
        $media = $this->media()->where('collection_name', $collection)->first();
        return $media ? $media->url : ($default ?? asset('assets/img/no_image.png'));
    }

    public function clearMediaCollection($collection = 'default')
    {
        $medias = $this->media()->where('collection_name', $collection)->get();
        foreach ($medias as $media) {
            if (Storage::disk('public')->exists($media->file_path)) {
                Storage::disk('public')->delete($media->file_path);
            }
            $media->delete();
        }
    }
}
