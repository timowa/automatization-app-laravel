<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\PublishWeeklySummaryJob;
use App\Models\Agent;
use App\Models\VkUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestWeeklySummaryCommand extends Command
{
    protected $signature = 'vk:test-summary {agentId}';
    protected $description = 'Тестовый запуск еженедельной сводки для одного агента';

    public function handle(): int
    {
        $agentId = (int) $this->argument('agentId');

        $agent = Agent::find($agentId);
        if (!$agent) {
            $this->error("Агент с id {$agentId} не найден");
            return self::FAILURE;
        }

        /** @var VkUser|null $vkUser */
        $vkUser = $agent->vkUser;
        if (!$vkUser || $vkUser->getToken() === '') {
            $this->error('Для агента не задан токен');
            Log::channel('job')->warning('Тестовая сводка не запущена: не задан токен', [
                'agent_id' => $agentId,
            ]);
            return self::FAILURE;
        }

        PublishWeeklySummaryJob::dispatch($agentId);

        $this->info("Тестовая сводка для агента {$agentId} поставлена в очередь");
        return self::SUCCESS;
    }
}
