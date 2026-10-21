<?php

declare(strict_types=1);

namespace App\Enums;

enum StandardTextKey: string
{
    case BirthdayWishMale = 'birthday_wish_male';
    case BirthdayWishFemale = 'birthday_wish_female';
    case BirthdayDiscount = 'birthday_discount';
    case FriendRequestWelcome = 'friend_request_welcome';

    public function label(): string
    {
        return match ($this) {
            self::BirthdayWishMale => 'Поздравление для мужчин',
            self::BirthdayWishFemale => 'Поздравление для женщин',
            self::BirthdayDiscount => 'Скидка на день рождения',
            self::FriendRequestWelcome => 'Автоприветствие',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
