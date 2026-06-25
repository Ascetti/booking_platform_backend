<?php

namespace App\Models;

use App\Enums\BookingStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    /** @use HasFactory<\Database\Factories\BookingFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'hotel_id',
        'room_category_id',
        'room_id',
        'rate_plan_id',
        'status_id',
        'check_in_date',
        'check_out_date',
        'adults_count',
        'children_count',
        'room_price_at_booking',
        'total_price',
        'comment'
    ];

    protected function casts()
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'adults_count' => 'integer',
            'children_count' => 'integer',
            'room_price_at_booking' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RoomCategory::class, 'room_category_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class, 'rate_plan_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(BookingStatus::class, 'status_id');
    }

    public function guests(): BelongsToMany
    {
        return $this->belongsToMany(Guest::class, 'booking_guest')
            ->withPivot([
                'is_primary',
                'first_name',
                'last_name',
                'email',
                'phone'
            ]);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'booking_service')
            ->withPivot(['quantity', 'price_at_booking']);
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(BookingIntegration::class);
    }

    public function calculateNights(): int
    {
        return $this->check_in_date->diffInDays($this->check_out_date);
    }

    // Получить главного гостя
    public function getPrimaryGuest(): ?Guest
    {
        return $this->guests()->wherePivot('is_primary', true)->first();
    }

    // Проверки статуса — используем slug из BookingStatus
    public function isNew(): bool
    {
        return $this->status->slug === BookingStatusEnum::NEW->value;
    }

    public function isConfirmed(): bool
    {
        return $this->status->slug === BookingStatusEnum::CONFIRMED->value;
    }

    public function isCheckedIn(): bool
    {
        return $this->status->slug === BookingStatusEnum::CHECKED_IN->value;
    }

    public function isCheckedOut(): bool
    {
        return $this->status->slug === BookingStatusEnum::CHECKED_OUT->value;
    }

    public function isCancelled(): bool
    {
        return $this->status->slug === BookingStatusEnum::CANCELLED->value;
    }

    public function isNoShow(): bool
    {
        return $this->status->slug === BookingStatusEnum::NO_SHOW->value;
    }

    // Можно ли изменить бронирование
    public function isModifiable(): bool
    {
        return $this->isNew() || $this->isConfirmed();
    }

    // Можно ли отменить
    public function canBeCancelled(): bool
    {
        return $this->isNew() || $this->isConfirmed();
    }
}
