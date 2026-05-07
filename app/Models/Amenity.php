<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Amenity extends Model
{
    // use SoftDeletes;

    protected $fillable = ['name', 'icon_src'];

    public function categories(): BelongsToMany {
        return $this->belongsToMany(RoomCategory::class, 'amenity_room_category');
    }
}
