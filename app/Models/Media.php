<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Media extends Model
{
    /** @use HasFactory<\Database\Factories\MediaFactory> */
    use HasFactory;

    protected $fillable = ['hotel_id', 'room_category_id', 'src'];

    public function category(): BelongsTo {
        return $this->belongsTo(RoomCategory::class, 'room_category_id');
    }

    public function hotel(): BelongsTo {
        return $this->belongsTo(Hotel::class);
    }
}
