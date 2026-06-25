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
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    // public function isPlatformStaff(): bool
    // {
    //     return $this->roles()->wherePivotNull('hotel_id')->exists();
    // }

    public function isPlatformStaff(): bool
    {
        return $this->roles()
            ->whereNull('role_user.hotel_id')
            ->exists();
    }

    // public function isHotelStaff(): bool
    // {
    //     return $this->roles()->wherePivotNotNull('hotel_id')->exists();
    // }

    public function isHotelStaff(): bool
    {
        return $this->roles()
            ->whereNotNull('role_user.hotel_id')
            ->exists();
    }

    // public function belongsToHotel(Hotel|int $hotelId): bool
    // {
    //     $id = $hotelId instanceof \App\Models\Hotel ? $hotelId->id : $hotelId;

    //     return $this->roles()
    //         ->wherePivot('hotel_id', $id)
    //         ->exists();
    // }

    public function belongsToHotel(Hotel|int $hotel): bool
    {
        $id = $hotel instanceof Hotel ? $hotel->id : $hotel;

        return $this->roles()
            ->where('role_user.hotel_id', $id)
            ->exists();
    }

    // public function hasPermission(PermissionEnum $permission, ?int $hotelId = null): bool
    // {
    //     return $this->roles()
    //         ->when($hotelId, function ($query) use ($hotelId) {
    //             $query->wherePivot('hotel_id', $hotelId);
    //         }, function ($query) {
    //             $query->wherePivotNull('hotel_id');
    //         })
    //         ->whereHas('permissions', function ($query) use ($permission) {
    //             $query->where('slug', $permission->value);
    //         })
    //         ->exists();
    // }

    public function hasPermission(PermissionEnum $permission, ?int $hotelId = null): bool
    {
        return $this->roles()
            ->when($hotelId, function ($query) use ($hotelId) {
                $query->where('role_user.hotel_id', $hotelId);
            }, function ($query) {
                $query->whereNull('role_user.hotel_id');
            })
            ->whereHas('permissions', function ($query) use ($permission) {
                $query->where('slug', $permission->value);
            })
            ->exists();
    }

    public function hasGlobalPermission(PermissionEnum $permission): bool
    {
        return $this->roles()
            ->whereHas('permissions', fn(Builder $query) => $query->where('slug', $permission->value))
            ->exists();
    }

    // public function hasHotelPermission(PermissionEnum $permission, int $hotelId): bool
    // {
    //     return $this->roles()
    //         ->wherePivot('hotel_id', $hotelId)
    //         ->whereHas('permissions', fn (Builder $query) => $query->where('slug', $permission->value))
    //         ->exists();
    // }

    public function hasHotelPermission(PermissionEnum $permission, int $hotelId): bool
    {
        return $this->roles()
            ->where('role_user.hotel_id', $hotelId)
            ->whereHas('permissions', fn(Builder $query) => $query->where('slug', $permission->value))
            ->exists();
    }

    public function hasRole(RoleEnum $role): bool
    {
        return $this->roles()
            ->where('slug', $role->value)
            ->exists();
    }
}
