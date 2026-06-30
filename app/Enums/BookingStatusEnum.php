<?php

namespace App\Enums;

enum BookingStatusEnum: string
{
    case NEW = 'new';
    case CONFIRMED = 'confirmed';
    case CHECKED_IN = 'checked_in';
    case CHECKED_OUT = 'checked_out';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'Новое',
            self::CONFIRMED => 'Подтверждено',
            self::CHECKED_IN => 'Заселен',
            self::CHECKED_OUT => 'Выселен',
            self::CANCELLED => 'Отменено',
        };
    }

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::NEW        => [self::CONFIRMED, self::CANCELLED],
            self::CONFIRMED  => [self::CHECKED_IN, self::CANCELLED],
            self::CHECKED_IN => [self::CHECKED_OUT, self::CONFIRMED],
            self::CHECKED_OUT => [],
            self::CANCELLED => [self::NEW],
        };
    }

    // Можно ли перейти в указанный статус
    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions());
    }
}
