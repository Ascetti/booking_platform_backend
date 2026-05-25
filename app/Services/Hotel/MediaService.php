<?php

namespace App\Services\Hotel;

use App\Models\Hotel;
use App\Models\Media;
use App\Models\RoomCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MediaService
{
    public function createMedia(RoomCategory $category, UploadedFile $file): Media
    {
        $path = "hotels/{$category->hotel_id}/room-categories/{$category->id}";

        $filePath = $file->store($path, 'public');

        try {
            return $category->media()->create(['src' => $filePath,]);
        } catch (\Exception $e) {
            Storage::disk('public')->delete($filePath);
            throw $e;
        }
    }

    public function deleteMedia(Media $media): bool
    {
        if (Storage::disk('public')->exists($media->src)) {
            Storage::disk('public')->delete($media->src);
        }

        return $media->delete();
    }
}