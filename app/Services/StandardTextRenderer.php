<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AgentVkFriend;
use App\Models\VkUser;

final class StandardTextRenderer
{
    public const BROKERPLUS = 'Брокер Плюс';

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function placeholders(): array
    {
        return [
            ['key' => 'agent_name', 'label' => 'Имя агента'],
            ['key' => 'agent_lastname', 'label' => 'Фамилия агента'],
            ['key' => 'brokerplus', 'label' => 'Название организации'],
            ['key' => 'friend_name', 'label' => 'Имя друга'],
            ['key' => 'friend_lastname', 'label' => 'Фамилия друга'],
        ];
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public static function render(string $template, array $values = []): string
    {
        $replacements = [
            '{{agent_name}}' => (string) ($values['agent_name'] ?? ''),
            '{{agent_lastname}}' => (string) ($values['agent_lastname'] ?? ''),
            '{{brokerplus}}' => (string) ($values['brokerplus'] ?? self::BROKERPLUS),
            '{{friend_name}}' => (string) ($values['friend_name'] ?? ''),
            '{{friend_lastname}}' => (string) ($values['friend_lastname'] ?? ''),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    public static function contextFrom(?VkUser $vkUser = null, ?AgentVkFriend $friend = null): array
    {
        return [
            'agent_name' => (string) ($vkUser?->first_name ?? ''),
            'agent_lastname' => (string) ($vkUser?->last_name ?? ''),
            'brokerplus' => self::BROKERPLUS,
            'friend_name' => (string) ($friend?->first_name ?? ''),
            'friend_lastname' => (string) ($friend?->last_name ?? ''),
        ];
    }
}
