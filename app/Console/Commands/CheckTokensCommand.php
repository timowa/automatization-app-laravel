<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\VkUser;
use App\Services\Vk\VkApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckTokensCommand extends Command
{
    protected $signature = 'vk:check-tokens';
    protected $description = 'Проверка доступности VK-токенов и уведомление';

    public function handle(VkApiService $vkApi): int
    {
        $logger = Log::channel('check_tokens');

        $vkUsers = VkUser::all();
        $users = [];

        foreach ($vkUsers as $vkUser) {
            $vkApi->setToken($vkUser->getToken());
            $users[$vkUser->id] = $vkApi->checkToken();
            usleep(300_000);
        }

        if (!empty($users)) {
            $cases = [];
            foreach ($users as $userId => $isAvailable) {
                $cases[] = "WHEN `id` = {$userId} THEN " . ((int) $isAvailable);
            }
            $casesSql = implode("\n", $cases);
            \Illuminate\Support\Facades\DB::statement("UPDATE `vk_users` SET `is_token_available` = CASE {$casesSql} ELSE 0 END, `is_token_valid` = CASE {$casesSql} ELSE 0 END WHERE 1");
        }

        $unavailable = VkUser::where('is_token_available', false)->get();
        if ($unavailable->isNotEmpty()) {
            $agents = \App\Models\Agent::whereIn('id', $unavailable->pluck('agent_id'))->get()->keyBy('id');
            $lines = [];
            foreach ($unavailable as $vkUser) {
                $agent = $agents->get($vkUser->agent_id);
                $lines[] = ($agent->name ?? 'Агент') . '[https://vk.ru/id' . $vkUser->vk_user_id . ']';
            }
            $text = implode("\n", $lines);

            try {
                $vkApi->sendTokensMessage($text);
                $logger->info($text);
            } catch (\Exception $e) {
                $logger->error($e->getMessage());
            }
        }

        $this->info('Проверка токенов завершена');
        return self::SUCCESS;
    }
}
