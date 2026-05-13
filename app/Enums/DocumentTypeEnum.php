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
            self::PASSPORT => 'passport',
            self::INTERNATIONAL_PASSPORT => 'international_passport',
            self::BIRTH_CERTIFICATE => 'birth_certificate',
            self::DRIVING_LICENSE => 'driving_license',
        };
    }
}
