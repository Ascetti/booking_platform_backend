<?php

namespace App\Enums;

enum BookingStatusEnum: string
{
    case NEW = 'new';
    case CONFIRMED = 'confirmed';
    case CHECKED_IN = 'checked_in';
    case CHECKED_OUT = 'checked_out';
    case CANCELLED = 'cancelled';
    case NO_SHOW = 'no_show';

    public function label(): string
    {
        return match($this) {
            self::NEW => 'new',
            self::CONFIRMED => 'confirmed',
            self::CHECKED_IN => 'checked in',
            self::CHECKED_OUT => 'checked out',
            self::CANCELLED => 'cancelled',
            self::NO_SHOW => 'no show',
        };
    }

    public function allowedTransitions(): array
    {
        return match($this) {
            self::NEW        => [self::CONFIRMED, self::CANCELLED],
            self::CONFIRMED  => [self::CHECKED_IN, self::CANCELLED, self::NO_SHOW],
            self::CHECKED_IN => [self::CHECKED_OUT],
            self::CHECKED_OUT => [],
            self::CANCELLED  => [],
            self::NO_SHOW    => [],
        };
    }

    // Можно ли перейти в указанный статус
    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions());
    }
}