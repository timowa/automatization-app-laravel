<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Helpers\BirthdayWishEligibility;
use App\Models\Agent;
use App\Models\AgentVkFriend;
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

    public function handle(VkApiService $vkApi): void
    {
        $agent = Agent::with('setting')->find($this->agentId);
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

        $setting = $agent->setting;
        if (! $setting) {
            Log::channel('job')->warning('Поздравление с днём рождения не отправлено: настройки не найдены', [
                'agent_id' => $this->agentId,
                'friend_user_id' => $this->friendUserId,
            ]);

            return;
        }

        if (BirthdayWishEligibility::wasWishedThisYear($friend->last_birthday_wish_at)) {
            Log::channel('job')->warning('Поздравление с днём рождения не отправлено: уже поздравляли в этом году', [
                'agent_id' => $this->agentId,
                'friend_user_id' => $this->friendUserId,
            ]);

            return;
        }

        if (! BirthdayWishEligibility::ageInRange($friend->bdate)) {
            Log::channel('job')->warning('Поздравление с днём рождения не отправлено: возраст вне диапазона 18–70 или нет года рождения', [
                'agent_id' => $this->agentId,
                'friend_user_id' => $this->friendUserId,
            ]);

            return;
        }

        $message = BirthdayWishEligibility::messageForFriend($setting, $friend->sex);
        if ($message === null) {
            Log::channel('job')->warning('Поздравление с днём рождения не отправлено: нет подходящего текста или выключена настройка', [
                'agent_id' => $this->agentId,
                'friend_user_id' => $this->friendUserId,
                'sex' => $friend->sex,
            ]);

            return;
        }

        try {
            $vkApi->setToken($vkUser->getToken());
            $vkApi->sendDirectMessage($this->friendUserId, $message);

            $friend->last_birthday_wish_at = now()->toDateString();
            $friend->save();

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
        }
    }
}
