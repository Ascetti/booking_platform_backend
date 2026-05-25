<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Hotel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'address', 'phone', 'email', 'description', 'timezone', 'child_age_threshold',
        'check_in_time', 'check_out_time'
    ];

    protected function casts()
    {
        return [
            'child_age_threshold' => 'integer',
            'check_in_time' => 'datetime:H:i',
            'check_out_time' => 'datetime:H:i',
        ];
    }

    protected function phone(): Attribute
    {
        return Attribute::make(
            set: function (?string $value) {
                if (!$value) {
                    return null;
                }
                $digits = preg_replace('/\D/', '', $value);
                if (strlen($digits) === 11) {
                    $digits = substr($digits, 1);
                }
                return sprintf(
                    '+7 (%s) %s %s-%s',
                    substr($digits, 0, 3),
                    substr($digits, 3, 3),
                    substr($digits, 6, 2),
                    substr($digits, 8, 2),
                );
            },
        );
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

    // public function rooms(): HasMany {
    //     return $this->hasMany(Room::class);
    // }

    public function plans(): HasMany {
        return $this->hasMany(RatePlan::class);
    }

    public function services(): HasMany {
        return $this->hasMany(Service::class);
    }

    public function bookings(): HasMany {
        return $this->hasMany(Booking::class);
    }

    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->isPlatformStaff()) {
            return $query;
        }

        return $query->whereHas('users', fn (Builder $query) => 
            $query->where('users.id', $user->id)
        );
    }
}
