<?php

namespace App\Enums;

enum ServiceTypeEnum: string
{
    case PER_STAY = 'per_stay';
    case PER_NIGHT = 'per_night';
    case PER_PERSON = 'per_person';
    case PER_PERSON_PER_NIGHT = 'per_person_per_night';

    public function label(): string
    {
        return match($this) {
            self::PER_STAY => 'per_stay',
            self::PER_NIGHT => 'per_night',
            self::PER_PERSON => 'per_person',
            self::PER_PERSON_PER_NIGHT => 'per_person_per_night',
        };
    }
}