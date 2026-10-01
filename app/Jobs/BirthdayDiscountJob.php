<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Agent;
use App\Models\AgentVkFriend;
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
        $agent = Agent::find($this->agentId);
        $friend = AgentVkFriend::where('agent_id', $this->agentId)
            ->where('user_id', $this->friendUserId)
            ->first();

        if (! $agent || ! $friend) {
            Log::channel('job')->warning('Скидка ко дню рождения не отправлена: данные не найдены', [
                'agent_id' => $this->agentId,
                'friend_user_id' => $this->friendUserId,
            ]);

            return;
        }

        $vkUser = $agent->vkUser;
        if (! $vkUser || $vkUser->getToken() === '' || ! $vkUser->is_token_available) {
            Log::channel('job')->warning('Скидка ко дню рождения не отправлена: токен недоступен', [
                'agent_id' => $this->agentId,
                'friend_user_id' => $this->friendUserId,
            ]);

            return;
        }

        if (! $agent->setting?->wish_happy_birthday) {
            Log::channel('job')->warning('Скидка ко дню рождения отключена в настройках агента', [
                'agent_id' => $this->agentId,
                'friend_user_id' => $this->friendUserId,
            ]);

            return;
        }

        try {
            $vkApi->setToken($vkUser->getToken());
            $vkApi->sendDirectMessage(
                $this->friendUserId,
                $textGenerator->generate($friend->first_name, $agent->name)
            );

            Log::channel('job')->info('Скидка ко дню рождения отправлена', [
                'agent_id' => $this->agentId,
                'friend_user_id' => $this->friendUserId,
            ]);
        } catch (\Throwable $e) {
            Log::channel('job')->warning('Ошибка отправки скидки ко дню рождения', [
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
