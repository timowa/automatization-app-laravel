<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\VkPostStat;
use App\Models\VkUser;
use App\Models\VkWallPost;
use App\Services\Vk\VkApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetStatsCommand extends Command
{
    protected $signature = 'vk:get-stats';
    protected $description = 'Сбор статистики просмотров VK-постов';

    public function handle(VkApiService $vkApi): int
    {
        $logger = Log::channel('stats');
        $logger->info('Процесс сбора статистики запущен');

        $posts = VkWallPost::with('offer')
            ->whereHas('offer', fn ($q) => $q->where('is_active', true))
            ->get();

        $postsByVkUser = [];
        foreach ($posts as $post) {
            $postsByVkUser[(int) $post->owner_id][] = $post;
        }

        $vkUsers = VkUser::whereIn('vk_user_id', array_keys($postsByVkUser))->get()->keyBy('vk_user_id');

        $data = [];
        foreach ($postsByVkUser as $userId => $userPosts) {
            $vkUser = $vkUsers->get((string) $userId);
            if (!$vkUser || $vkUser->getToken() === '') {
                continue;
            }

            $vkApi->setToken($vkUser->getToken());
            $postIds = array_map(fn (VkWallPost $post) => $post->getFullId(), $userPosts);
            $stats = $vkApi->getPostsStats($postIds);

            $indexStats = [];
            foreach ($stats['items'] ?? [] as $stat) {
                $postFullId = $stat['owner_id'] . '_' . $stat['id'];
                $indexStats[$postFullId] = $stat;
            }

            foreach ($userPosts as $post) {
                $fullId = $post->getFullId();
                if (isset($indexStats[$fullId]['views']['count'])) {
                    $data[] = [
                        'vk_post_id' => $post->id,
                        'views' => (int) $indexStats[$fullId]['views']['count'],
                    ];
                }
            }

            sleep(1);
        }

        if (!empty($data)) {
            VkPostStat::insert($data);
        }

        $logger->info('Процесс сбора статистики завершен. Вставлено ' . count($data) . ' записей');

        $this->info('Статистика собрана');
        return self::SUCCESS;
    }
}
