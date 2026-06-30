<?php

namespace App\Models;

use App\Enums\BookingStatusEnum;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoomCategory extends Model
{
    /** @use HasFactory<\Database\Factories\RoomCategoryFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'hotel_id',
        'name',
        'description',
        'area',
        'base_capacity',
        'extra_capacity',
        'bedding_options'
    ];

    protected function casts(): array
    {
        return [
            'area' => 'integer',
            'base_capacity' => 'integer',
            'extra_capacity' => 'integer',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'amenity_room_category');
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(RatePrice::class, 'room_category_id');
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(RateOverride::class, 'room_category_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'room_category_id');
    }

    public function canAccommodate(int $adults, int $children, int $childrenWithoutPlace = 1): bool
    {
        // Считаем сколько мест занимают гости
        $childrenWithPlace = max(0, $children - $childrenWithoutPlace);
        $occupiedPlaces = $adults + $childrenWithPlace;
        $maxPlaces = $this->base_capacity + $this->extra_capacity;
        return $occupiedPlaces <= $maxPlaces;
    }

    public function getAvailableRoomsCount(Carbon $checkIn, Carbon $checkOut, ?int $excludeBookingId = null): int
    {
        // Статусы при которых номер считается занятым
        $activeStatuses = [
            BookingStatusEnum::NEW->value,
            BookingStatusEnum::CONFIRMED->value,
            BookingStatusEnum::CHECKED_IN->value,
        ];

        // Общее количество активных номеров в категории
        $totalActiveRooms = $this->rooms()
            ->where('is_active', true)
            ->count();

        // Бронирования которые пересекаются с нашим периодом
        // Формула пересечения: заезд брони < наш выезд И выезд брони > наш заезд
        $overlappingBookings = $this->bookings()
            ->whereHas('status', function ($query) use ($activeStatuses) {
                $query->whereIn('slug', $activeStatuses);
            })
            ->where('check_in_date', '<', $checkOut)
            ->where('check_out_date', '>', $checkIn)
            ->when($excludeBookingId, fn($q) => $q->where('id', '!=', $excludeBookingId))
            ->get();

        // номера назначены
        $assignedCount = $overlappingBookings
            ->whereNotNull('room_id')
            ->count();

        // номера не назначены
        $unassignedCount = $overlappingBookings
            ->whereNull('room_id')
            ->count();

        $available = $totalActiveRooms - $assignedCount - $unassignedCount;

        // не может быть отрицательным
        return max(0, $available);
    }

    public function hasAvailableRooms(Carbon $checkIn, Carbon $checkOut, ?int $excludeBookingId = null): bool
    {
        return $this->getAvailableRoomsCount($checkIn, $checkOut, $excludeBookingId) > 0;
    }

    public function getTotalRooms()
    {
        return $this->rooms()->count();
    }

    public function getTotalActiveRooms()
    {
        return $this->rooms()
            ->where('is_active', true)
            ->count();
    }
}
