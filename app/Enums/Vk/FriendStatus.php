<?php

declare(strict_types=1);

namespace App\Enums\Vk;

use App\Traits\EnumHasLabel;

enum FriendStatus: int
{
    use EnumHasLabel;

    case NOT_FRIEND = 0;
    case OUTGOING_REQUEST = 1;
    case INCOMING_REQUEST = 2;
    case FRIEND = 3;

    public function label(): string
    {
        return match ($this) {
            self::NOT_FRIEND => 'Не является другом',
            self::OUTGOING_REQUEST => 'Отправлена заявка / подписка пользователю',
            self::INCOMING_REQUEST => 'Имеется входящая заявка / подписка от пользователя',
            self::FRIEND => 'Является другом',
        };
    }

    public static function labelFor(?int $value): ?string
    {
        return $value === null ? null : self::tryFrom($value)?->label();
    }
}
