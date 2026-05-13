<?php

namespace App\Services\Hotel;

use App\Models\Hotel;
use App\Models\RateOverride;
use App\Models\RatePrice;
use App\Models\RoomCategory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RoomCategoryService
{

    public function createCategory(Hotel $hotel, array $data): RoomCategory
    {
        return DB::transaction(function () use ($hotel, $data) {
            $category = $hotel->categories()->create(Arr::except($data, ['amenities']));

            if (isset($data['amenities'])) {
                $category->amenities()->sync($data['amenities']);
            }

            return $category->load('amenities');
        });
    }

    public function updateCategory(RoomCategory $category, array $data): RoomCategory
    {
        return DB::transaction(function () use ($category, $data) {
            $category->update(Arr::except($data, ['amenities']));

            if (array_key_exists('amenities', $data)) {
                $category->amenities()->sync($data['amenities'] ?? []);
            }

            return $category->load(['amenities', 'media']);
        });
    }

    public function deleteCategory(RoomCategory $category): bool
    {
        return DB::transaction(function () use ($category) {
            RatePrice::where('room_category_id', $category->id)->delete();
            RateOverride::where('room_category_id', $category->id)->delete();
            $category->rooms()->delete();
            return $category->delete();
        });
    }
}
