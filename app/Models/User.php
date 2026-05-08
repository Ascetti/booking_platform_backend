<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    protected $fillable = ['name', 'email', 'phone', 'password'];

    protected $hidden = ['password', 'remember_token'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withPivot('hotel_id')
            ->withTimestamps();
    }

    public function hotels(): BelongsToMany
    {
        return $this->belongsToMany(Hotel::class, 'role_user')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    public function isPlatformAdmin(): bool {
        return $this->roles()
            ->where('name', RoleEnum::PLATFORM_ADMIN->value)
            ->wherePivot('hotel_id', null)
            ->exists();
    }

    public function isHotelAdmin(?int $hotelId = null): bool {
        return $this->roles()
            ->where('name', RoleEnum::HOTEL_ADMIN->value)
            ->when($hotelId, fn (Builder $query) => $query->wherePivot('hotel_id', $hotelId))
            ->exists();
    }

    // public function hasPermission(string $permission, ?int $hotelId = null): bool
    // {
    //     return $this->roles()
    //         ->when($hotelId, function ($query) use ($hotelId) {
    //             $query->wherePivot('hotel_id', $hotelId);
    //         })
    //         ->whereHas('permissions', function ($query) use ($permission) {
    //             $query->where('name', $permission);
    //         })
    //         ->exists();
    // }

    public function hasGlobalPermission(PermissionEnum $permission): bool {
        return $this->roles()
            // ->wherePivot('hotel_id', null)
            ->whereHas('permissions', fn (Builder $query) => $query->where('name', $permission->value))
            ->exists();
    }

    public function hasHotelPermission(PermissionEnum $permission, int $hotelId): bool {
        return $this->roles()
            ->wherePivot('hotel_id', $hotelId)
            ->whereHas('permissions', fn (Builder $query) => $query->where('name', $permission->value))
            ->exists();
    }
}
