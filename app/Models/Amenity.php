<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Amenity extends Model
{
    protected $fillable = ['slug', 'name', 'icon_src'];

    public function categories(): BelongsToMany {
        return $this->belongsToMany(RoomCategory::class, 'amenity_room_category');
    }
}
