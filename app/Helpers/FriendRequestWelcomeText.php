<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Setting;

final class FriendRequestWelcomeText
{
    public static function resolve(?Setting $setting, string $agentName): string
    {
        $custom = trim((string) ($setting?->friend_request_welcome_text ?? ''));
        if ($custom !== '') {
            return $custom;
        }

        $name = trim($agentName) !== '' ? trim($agentName) : 'агент';

        return 'Здравствуйте! Рад знакомству. Я '.$name
            .', агент недвижимости компании «Брокер Плюс». '
            .'Вы хотели задать вопрос по недвижимости или у вас другое дело?';
    }

    public static function photoAttachment(int $ownerId, int $mediaId): string
    {
        return 'photo'.$ownerId.'_'.$mediaId;
    }
}
