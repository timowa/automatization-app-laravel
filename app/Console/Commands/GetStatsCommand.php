<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\OfferStatus;
use App\Models\VkPostStat;
use App\Models\VkUser;
use App\Models\VkWallPost;
use App\Services\Vk\VkApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class GetStatsCommand extends Command
{
    protected $signature = 'vk:get-stats';
    protected $description = 'Сбор статистики просмотров VK-постов';

    public function handle(VkApiService $vkApi): int
    {
        $logger = Log::channel('stats');
        $jobLogger = Log::channel('job');

        $logger->info('Процесс сбора статистики запущен');

        $activeOfferIds = DB::table('offers')
            ->where('status', OfferStatus::ACTIVE->value)
            ->pluck('id');

        if ($activeOfferIds->isEmpty()) {
            $logger->info('Нет активных офферов');
            return self::SUCCESS;
        }

        $agentIds = DB::table('offers')
            ->where('status', OfferStatus::ACTIVE->value)
            ->distinct()
            ->pluck('agent_id');

        $posts = VkWallPost::with('offer')
            ->whereIn('offer_id', $activeOfferIds)
            ->get();

        if ($posts->isEmpty()) {
            $logger->info('Нет постов для сбора статистики');
            return self::SUCCESS;
        }

        $vkUsers = VkUser::whereIn('agent_id', $agentIds)->get()->keyBy('agent_id');

        $postsByOwnerId = $posts->groupBy('owner_id');

        $ownerIdToAgentId = [];
        foreach ($posts as $post) {
            $ownerIdToAgentId[(int) $post->owner_id] = (int) $post->offer->agent_id;
        }

        $data = [];
        foreach ($postsByOwnerId as $ownerId => $userPosts) {
            $agentId = $ownerIdToAgentId[(int) $ownerId] ?? null;
            $vkUser = $agentId !== null ? $vkUsers->get($agentId) : null;

            if ($vkUser === null || $vkUser->getToken() === '' || !$vkUser->is_token_available) {
                $logger->warning('Пропуск агента: нет токена или токен недоступен', [
                    'vk_user_id' => $ownerId,
                ]);
                continue;
            }

            $vkApi->setToken($vkUser->getToken());

            $postIds = $userPosts->map(fn (VkWallPost $post) => $post->getFullId())->toArray();
            $batches = array_chunk($postIds, 100);

            foreach ($batches as $batch) {
                try {
                    $response = $vkApi->getPostsStats($batch);

                    $indexStats = [];
                    foreach ($response['items'] ?? [] as $stat) {
                        if (isset($stat['is_deleted']) && $stat['is_deleted'] === true) {
                            continue;
                        }
                        $postFullId = $stat['owner_id'] . '_' . $stat['id'];
                        $indexStats[$postFullId] = $stat;
                    }

                    foreach ($userPosts as $post) {
                        $fullId = $post->getFullId();
                        if (isset($indexStats[$fullId])) {
                            $stat = $indexStats[$fullId];
                            $data[] = [
                                'vk_post_id' => $post->id,
                                'views' => (int) ($stat['views']['count'] ?? 0),
                                'reposts' => (int) ($stat['reposts']['count'] ?? 0),
                                'likes' => (int) ($stat['likes']['count'] ?? 0),
                                'comments' => (int) ($stat['comments']['count'] ?? 0),
                            ];
                        }
                    }
                } catch (Throwable $e) {
                    $logger->warning('Ошибка получения статистики', [
                        'vk_user_id' => $ownerId,
                        'posts_count' => count($batch),
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
            }

            sleep(1);
        }

        try {
            if (!empty($data)) {
                VkPostStat::insert($data);
            }
        } catch (Throwable $e) {
            $data = [];
            $logger->warning('Ошибка при вставке данных в бд', ['exception' => $e]);
        }


        $logger->info('Процесс сбора статистики завершен. Вставлено ' . count($data) . ' записей');
        $jobLogger->info('Статистика собрана. Вставлено записей: ' . count($data));

        $this->info('Статистика собрана');
        return self::SUCCESS;
    }
}
