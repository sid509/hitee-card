<?php

namespace App\Traits;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;

trait HasMedia
{
    public function media()
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function addMedia($file, $collection = 'default', $customName = null)
    {
        $fileName = $customName ?? $file->getClientOriginalName();
        $path = $file->store('media', 'public');

        return $this->media()->create([
            'file_path' => $path,
            'file_name' => $fileName,
            'collection_name' => $collection,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
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
