<?php

declare(strict_types=1);

namespace App\Services\Vk\Message;

final class BirthdayWishTextGenerator
{
    private const TEXTS = [
        <<<'TEXT'
Здравствуйте, {{friend_name}}! Поздравляю Вас с днём рождения! 🎉

Желаю Вам здоровья, благополучия и побольше приятных событий в новом году жизни. Пусть рядом будут хорошие люди, а поводов для радости становится только больше.

Хорошего Вам дня и отличного настроения! 😊

С уважением, Команда Брокер Плюс, {{agent_name}}
TEXT,
        <<<'TEXT'
{{friend_name}}, здравствуйте! Поздравляю Вас с днём рождения! 🎉

Желаю, чтобы этот год был наполнен приятными событиями, хорошими встречами и новыми возможностями. Пусть всё складывается именно так, как Вам хочется, а дома и в жизни всегда царят тепло и уют.

Отличного Вам настроения и прекрасного дня! 😊

Команда Брокер Плюс, {{agent_name}}
TEXT,
    ];

    public function generate(string $friendName, string $agentName): string
    {
        return str_replace(
            ['{{friend_name}}', '{{agent_name}}'],
            [$friendName, $agentName],
            self::TEXTS[array_rand(self::TEXTS)]
        );
    }
}
