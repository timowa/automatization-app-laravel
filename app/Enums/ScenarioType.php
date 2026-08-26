<?php

namespace App\Enums;

use App\Traits\EnumHasLabel;

enum ScenarioType: string
{
    use EnumHasLabel;

    case ANNOUNCEMENT = 'announcement';
    case RENT = 'rent';
    case SALE = 'sale';
    case PRICE_CHANGED = 'price_changed';
    case AGENT_CHANGED = 'agent_changed';
    case BOOKING = 'booking';
    case SOLD = 'sold';
    case RENT_OUT = 'rent_out';
    case FEEDBACK = 'feedback';
    case WITHDRAWN = 'withdrawn';
    case DELAYED = 'delayed';
    case DELETED = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::ANNOUNCEMENT => 'Анонс',
            self::RENT => 'Аренда',
            self::SALE => 'Продажа',
            self::PRICE_CHANGED => 'Изменилась цена',
            self::AGENT_CHANGED => 'Изменился агент',
            self::BOOKING => 'Бронь',
            self::SOLD => 'Продано',
            self::RENT_OUT => 'Сдано',
            self::FEEDBACK => 'Отзыв',
            self::WITHDRAWN => 'Снято',
            self::DELAYED => 'Отложено',
            self::DELETED => 'Удалено',
        };
    }
}