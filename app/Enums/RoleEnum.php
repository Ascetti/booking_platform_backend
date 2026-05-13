<?php

namespace App\Enums;

enum RoleEnum: string
{
    case PLATFORM_ADMIN = 'platform_admin';
    case PLATFORM_SUPPORT = 'platform_support';
    case HOTEL_ADMIN = 'hotel_admin';
}
