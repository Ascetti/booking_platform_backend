<?php

namespace App\Enums;

enum MealPlanEnum: string
{
    case RO = 'RO'; // Room Only (Без питания)
    case BB = 'BB'; // Bed & Breakfast (Завтрак)
    case HB = 'HB'; // Half Board (Полупансион: завтрак + ужин)
    case FB = 'FB'; // Full Board (Полный пансион: завтрак + обед + ужин)
    case AI = 'AI'; // All Inclusive (Все включено)

    public function label(): string
    {
        return match($this) {
            self::RO => 'Room Only',
            self::BB => 'Bed & Breakfast',
            self::HB => 'Half Board',
            self::FB => 'Full Board',
            self::AI => 'All Inclusive',
        };
    }
}