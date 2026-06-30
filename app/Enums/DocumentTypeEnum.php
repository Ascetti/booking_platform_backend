<?php

namespace App\Enums;

enum DocumentTypeEnum: string
{
    case PASSPORT = 'passport';
    case INTERNATIONAL_PASSPORT = 'international_passport';
    case BIRTH_CERTIFICATE = 'birth_certificate';
    case DRIVING_LICENSE = 'driving_license';

    public function label(): string
    {
        return match($this) {
            self::PASSPORT => 'Паспорт',
            self::INTERNATIONAL_PASSPORT => 'Загранпаспорт',
            self::BIRTH_CERTIFICATE => 'Свидетельство о рождении',
            self::DRIVING_LICENSE => 'Водительское удостоверение',
        };
    }
}
