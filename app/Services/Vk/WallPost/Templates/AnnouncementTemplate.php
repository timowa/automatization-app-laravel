<?php

declare(strict_types=1);

namespace App\Services\Vk\WallPost\Templates;

use App\Interfaces\VkPostTemplateInterface;
use App\Services\Vk\WallPost\VkPostContext;

class AnnouncementTemplate implements VkPostTemplateInterface
{
    public function generate(VkPostContext $context): string
    {
        return <<<TEXT
        СКОРО В ПРОДАЖЕ КВАРТИРА в г. {$context->cityName}!

        Адрес: {$context->address}
        Комнаты: {$context->rooms}
        Общая площадь: {$context->getArea()}

        👍 Цена: на согласовании, следите за новыми постами!

        КОНТАКТЫ:

        Агентство: Брокер Плюс
        Агент собственника: {$context->agentName}
        📞 Звоните: {$context->getAgentPhone()}

        --
        Уважаемый покупатель!
        Агентство недвижимости “Брокер Плюс” начало юридическую проверку данной квартиры и согласование цены с собственником для дальнейшего распространения в рекламе.
        Ожидайте в ближайшее время полную информацию, но если вам уже сейчас интересен этот объект, то пишите или звоните агенту и мы сообщим цену и условия покупки в первую очередь именно вам.
        🔥 Оставьте заявку и узнайте о начале продажи первым!

        #{$context->getHasTag()}
        TEXT;
    }
}
