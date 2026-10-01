<?php

declare(strict_types=1);

namespace App\Services\Vk\Message;

final class BirthdayDiscountTextGenerator
{
    private const TEXTS = [
        <<<'TEXT'
{{friend_name}}, ещё раз поздравляю Вас с днём рождения!

В честь праздника хочу подарить Вам персональную скидку на мои услуги. Если вопрос с недвижимостью станет актуальным, напишите мне — с удовольствием расскажу подробности.

С уважением, Команда Брокер Плюс,  {{agent_name}}
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
