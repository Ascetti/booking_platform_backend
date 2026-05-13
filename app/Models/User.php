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

    public function isPlatformAdmin(): bool
    {
        return $this->roles()
            ->where('slug', RoleEnum::PLATFORM_ADMIN->value)
            ->wherePivot('hotel_id', null)
            ->exists();
    }

    public function isHotelAdmin(?int $hotelId = null): bool
    {
        return $this->roles()
            ->where('slug', RoleEnum::HOTEL_ADMIN->value)
            ->when($hotelId, fn(Builder $query) => $query->wherePivot('hotel_id', $hotelId))
            ->exists();
    }

    public function isPlatformStaff(): bool
    {
        return $this->roles()->wherePivot('hotel_id', null)->exists();
    }

    public function isHotelStaff(): bool
    {
        return $this->roles()->wherePivotNotNull('hotel_id')->exists();
    }

    public function belongsToHotel(Hotel|int $hotelId): bool
    {
        $id = $hotelId instanceof \App\Models\Hotel ? $hotelId->id : $hotelId;

        return $this->roles()
            ->wherePivot('hotel_id', $id)
            ->exists();
    }

    public function hasPermission(PermissionEnum $permission, ?int $hotelId = null): bool
    {
        return $this->roles()
            ->when($hotelId, function ($query) use ($hotelId) {
                $query->wherePivot('hotel_id', $hotelId);
            }, function ($query) {
                $query->wherePivot('hotel_id', null);
            })
            ->whereHas('permissions', function ($query) use ($permission) {
                $query->where('slug', $permission);
            })
            ->exists();
    }

    public function hasGlobalPermission(PermissionEnum $permission): bool
    {
        return $this->roles()
            ->whereHas('permissions', fn (Builder $query) => $query->where('slug', $permission->value))
            ->exists();
    }

    public function hasHotelPermission(PermissionEnum $permission, int $hotelId): bool
    {
        return $this->roles()
            ->wherePivot('hotel_id', $hotelId)
            ->whereHas('permissions', fn (Builder $query) => $query->where('slug', $permission->value))
            ->exists();
    }

    public function hasRole(RoleEnum $role) {
        return $this->roles()
            ->where('slug', $role->value)
            ->exists();
    }
}
