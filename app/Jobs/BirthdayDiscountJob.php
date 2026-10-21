<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Agent;
use App\Models\AgentVkFriend;
use App\Services\StandardTextRenderer;
use App\Services\Vk\Message\BirthdayDiscountTextGenerator;
use App\Services\Vk\VkApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BirthdayDiscountJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $agentId,
        public readonly int $friendUserId
    ) {}

    public function handle(VkApiService $vkApi, BirthdayDiscountTextGenerator $textGenerator): void
    {
        $agent = Agent::with(['setting', 'vkUser'])->find($this->agentId);
        $friend = AgentVkFriend::where('agent_id', $this->agentId)
            ->where('user_id', $this->friendUserId)
            ->first();

        if (! $agent || ! $friend) {
            Log::channel('job')->warning('Скидка на день рождения не отправлена: данные не найдены', [
                'agent_id' => $this->agentId,
                'friend_user_id' => $this->friendUserId,
            ]);

            return;
        }

        $vkUser = $agent->vkUser;
        if (! $vkUser || $vkUser->getToken() === '' || ! $vkUser->is_token_available) {
            Log::channel('job')->warning('Скидка на день рождения не отправлена: токен недоступен', [
                'agent_id' => $this->agentId,
                'friend_user_id' => $this->friendUserId,
            ]);

            return;
        }

        if (! $agent->setting?->wish_happy_birthday_male && ! $agent->setting?->wish_happy_birthday_female) {
            Log::channel('job')->warning('Скидка на день рождения не отправлена: отключена в настройках агента', [
                'agent_id' => $this->agentId,
                'friend_user_id' => $this->friendUserId,
            ]);

            return;
        }

        $template = $textGenerator->template();
        if ($template === null || trim($template) === '') {
            Log::channel('job')->warning('Скидка на день рождения не отправлена: нет текста', [
                'agent_id' => $this->agentId,
                'friend_user_id' => $this->friendUserId,
            ]);

            return;
        }

        $message = StandardTextRenderer::render(
            $template,
            StandardTextRenderer::contextFrom($vkUser, $friend)
        );

        try {
            $vkApi->setToken($vkUser->getToken());
            $vkApi->sendDirectMessage($this->friendUserId, $message);

            Log::channel('job')->info('Скидка на день рождения отправлена', [
                'agent_id' => $this->agentId,
                'friend_user_id' => $this->friendUserId,
            ]);
        } catch (\Throwable $e) {
            Log::channel('job')->warning('Ошибка отправки скидки на день рождения', [
                'agent_id' => $this->agentId,
                'friend_user_id' => $this->friendUserId,
            ]);
            Log::channel('vk')->error($e->getMessage(), [
                'agent_id' => $this->agentId,
                'friend_user_id' => $this->friendUserId,
            ]);
        }
    }
}
