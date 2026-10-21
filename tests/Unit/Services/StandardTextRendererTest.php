<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\AgentVkFriend;
use App\Models\VkUser;
use App\Services\StandardTextRenderer;
use Tests\TestCase;

class StandardTextRendererTest extends TestCase
{
    public function test_replaces_all_placeholders(): void
    {
        $result = StandardTextRenderer::render(
            '{{agent_name}} {{agent_lastname}} из {{brokerplus}} пишет {{friend_name}} {{friend_lastname}}',
            [
                'agent_name' => 'Ирина',
                'agent_lastname' => 'Петрова',
                'brokerplus' => 'БрокерПлюс',
                'friend_name' => 'Иван',
                'friend_lastname' => 'Сидоров',
            ]
        );

        $this->assertSame('Ирина Петрова из БрокерПлюс пишет Иван Сидоров', $result);
    }

    public function test_context_from_vk_user_and_friend(): void
    {
        $vkUser = new VkUser(['first_name' => 'Анна', 'last_name' => 'Козлова']);
        $friend = new AgentVkFriend(['first_name' => 'Пётр', 'last_name' => 'Орлов']);

        $context = StandardTextRenderer::contextFrom($vkUser, $friend);

        $this->assertSame('Анна', $context['agent_name']);
        $this->assertSame('Козлова', $context['agent_lastname']);
        $this->assertSame('БрокерПлюс', $context['brokerplus']);
        $this->assertSame('Пётр', $context['friend_name']);
        $this->assertSame('Орлов', $context['friend_lastname']);
    }

    public function test_missing_values_become_empty_strings(): void
    {
        $result = StandardTextRenderer::render('Привет, {{friend_name}}!');

        $this->assertSame('Привет, !', $result);
    }
}
