<?php

namespace App\Enums;

enum ServiceTypeEnum: string
{
    case PER_STAY = 'per_stay';
    case PER_SERVICE = 'per_service';
    case PER_NIGHT = 'per_night';
    case PER_PERSON = 'per_person';
    case PER_PERSON_PER_NIGHT = 'per_person_per_night';

    public function label(): string
    {
        return match ($this) {
            self::PER_STAY => 'единоразово',
            self::PER_SERVICE => 'за услугу',
            self::PER_NIGHT => 'за ночь',
            self::PER_PERSON => 'за человека',
            self::PER_PERSON_PER_NIGHT => 'за ночь за человека',
        };
    }

    public function hasQuantity(): bool
    {
        return match ($this) {
            self::PER_STAY => false,
            default        => true,
        };
    }
}
