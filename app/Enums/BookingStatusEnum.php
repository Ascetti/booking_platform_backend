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
    case MAINTENANCE = 'maintenance';
}