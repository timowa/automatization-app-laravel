<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Agent;
use App\Models\AgentVkFriend;
use App\Services\Vk\Message\BirthdayWishTextGenerator;
use App\Services\Vk\VkApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class WishHappyBirthdayJob implements ShouldQueue
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

    public function handle(VkApiService $vkApi, BirthdayWishTextGenerator $textGenerator): void
    {
        $agent = Agent::find($this->agentId);
        $friend = AgentVkFriend::where('agent_id', $this->agentId)
            ->where('user_id', $this->friendUserId)
            ->first();

        if (! $agent || ! $friend) {
            Log::channel('job')->warning('Поздравление с днём рождения не отправлено: данные не найдены', [
                'agent_id' => $this->agentId,
                'friend_user_id' => $this->friendUserId,
            ]);

            return;
        }

        $vkUser = $agent->vkUser;
        if (! $vkUser || $vkUser->getToken() === '' || ! $vkUser->is_token_available) {
            Log::channel('job')->warning('Поздравление с днём рождения не отправлено: токен недоступен', [
                'agent_id' => $this->agentId,
                'friend_user_id' => $this->friendUserId,
            ]);

            return;
        }

        if (! $agent->setting?->wish_happy_birthday) {
            Log::channel('job')->warning('Поздравление с днём рождения отключено в настройках агента', [
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

            Log::channel('job')->info('Поздравление с днём рождения отправлено', [
                'agent_id' => $this->agentId,
                'friend_user_id' => $this->friendUserId,
            ]);
        } catch (\Throwable $e) {
            Log::channel('job')->warning('Ошибка отправки поздравления с днём рождения', [
                'agent_id' => $this->agentId,
                'friend_user_id' => $this->friendUserId,
            ]);
            Log::channel('vk')->error($e->getMessage(), [
                'agent_id' => $this->agentId,
                'friend_user_id' => $this->friendUserId,
            ]);

            return;
        }

        try {
            BirthdayDiscountJob::dispatch($this->agentId, $this->friendUserId)
                ->delay(now()->addDay()->setTime(8, 0));

            Log::channel('job')->info('Задача отправки скидки ко дню рождения создана', [
                'agent_id' => $this->agentId,
                'friend_user_id' => $this->friendUserId,
            ]);
        } catch (\Throwable $e) {
            Log::channel('job')->warning('Ошибка создания задачи отправки скидки ко дню рождения', [
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
