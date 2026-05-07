<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

class Hotel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'phone',
        'email',
        'description',
        'timezone',
        'child_age_threshold',
        'check_in_time',
        'check_out_time'
    ];

    protected function casts()
    {
        return [
            'child_age_threshold' => 'integer',
            'check_in_time' => 'datetime:H:i',
            'check_out_time' => 'datetime:H:i',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    public function categories(): HasMany {
        return $this->hasMany(RoomCategory::class);
    }

    public function rooms(): HasMany {
        return $this->hasMany(Room::class);
    }

    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->isPlatformAdmin()) {
            return $query;
        }

        return $query->whereHas('users', fn (Builder $query) => 
            $query->where('users.id', $user->id)
        );
    }
}
