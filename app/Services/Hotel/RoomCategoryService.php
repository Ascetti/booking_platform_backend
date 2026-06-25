<?php

namespace App\Services\Hotel;

use App\Models\Hotel;
use App\Models\RateOverride;
use App\Models\RatePrice;
use App\Models\RoomCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RoomCategoryService
{

    public function createCategory(Hotel $hotel, array $data): RoomCategory
    {
        // return DB::transaction(function () use ($hotel, $data) {
        //     $category = $hotel->categories()->create(Arr::except($data, ['amenities']));

        //     if (isset($data['amenities'])) {
        //         $category->amenities()->sync($data['amenities']);
        //     }

        //     return $category->load('amenities');
        // });
        return $hotel->categories()->create($data);
    }

    public function updateCategory(RoomCategory $category, array $data): RoomCategory
    {
        // return DB::transaction(function () use ($category, $data) {
        //     $category->update(Arr::except($data, ['amenities']));

        //     if (array_key_exists('amenities', $data)) {
        //         $category->amenities()->sync($data['amenities'] ?? []);
        //     }

        //     return $category->load(['amenities', 'media']);
        // });
        $category->update($data);
        return $category;
    }

    public function deleteCategory(RoomCategory $category): bool
    {
        return DB::transaction(function () use ($category) {

            if ($category->bookings()->exists()) {
                throw new ConflictHttpException(
                    'Cannot delete room category with bookings.'
                );
            }
            RatePrice::where('room_category_id', $category->id)->delete();
            RateOverride::where('room_category_id', $category->id)->delete();
            $category->amenities()->detach();
            $category->media->each(function ($media) {
                app(MediaService::class)->deleteMedia($media);
            });
            $category->rooms()->delete();
            return $category->delete();
        });
    }

    public function syncAmenities(RoomCategory $category, array $amenities): Collection
    {
        $category->amenities()->sync($amenities);
        return $category->amenities()->get();
    }
}
