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
            self::RO => 'Без питания',
            self::BB => 'Завтрак',
            self::HB => 'Полупансион',
            self::FB => 'Полный пансион',
            self::AI => 'Все включено',
        };
    }
}