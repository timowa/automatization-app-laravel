<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\OfferStatus;
use App\Jobs\PublishWeeklySummaryJob;
use App\Models\Agent;
use App\Models\Offer;
use App\Models\VkUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PublishWeeklySummaryCommand extends Command
{
    protected $signature = 'vk:publish-weekly-summary';
    protected $description = 'Запуск еженедельной сводки активных офферов агентов';

    public function handle(): int
    {
        $agents = Agent::all();

        foreach ($agents as $agent) {
            /** @var VkUser|null $vkUser */
            $vkUser = $agent->vkUser;

            if (!$vkUser || $vkUser->getToken() === '') {
                Log::channel('job')->warning('Не задан токен для еженедельной сводки', [
                    'agent_id' => $agent->id,
                ]);
                continue;
            }

            $hasActive = Offer::where('agent_id', $agent->id)
                ->where('status', OfferStatus::ACTIVE->value)
                ->exists();

            if (!$hasActive) {
                continue;
            }

            $delayMinutes = rand(10, 60);
            PublishWeeklySummaryJob::dispatch($agent->id)
                ->delay(now()->addMinutes($delayMinutes));

            Log::channel('job')->info('Запланирована еженедельная сводка', [
                'agent_id' => $agent->id,
                'delay_minutes' => $delayMinutes,
            ]);
        }

        $this->info('Еженедельные сводки запланированы');
        return self::SUCCESS;
    }
}
