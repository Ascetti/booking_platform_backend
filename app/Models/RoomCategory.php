<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomCategory extends Model
{
    /** @use HasFactory<\Database\Factories\RoomCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'hotel_id', 'name', 'description', 'area', 
        'base_capacity', 'extra_capacity', 'bedding_options'
    ];

    protected function casts(): array
    {
        return [
            'area' => 'integer',
            'base_capacity' => 'integer',
            'extra_capacity' => 'integer',
        ];
    }

    public function hotel(): BelongsTo {
        return $this->belongsTo(Hotel::class);
    }

    public function rooms(): HasMany {
        return $this->hasMany(Room::class);
    }

    public function amenities(): BelongsToMany {
        return $this->belongsToMany(Amenity::class, 'amenity_room_category');
    }

    public function media(): HasMany {
        return $this->hasMany(Media::class);
    }
}
