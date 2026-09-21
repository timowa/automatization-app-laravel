<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ScenarioType;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VkWallPostController extends Controller
{
    public function index(): View
    {
        $posts = DB::table('vk_posts')
            ->join('offers', 'offers.id', '=', 'vk_posts.offer_id')
            ->join('publication_tasks', 'publication_tasks.id', '=', 'vk_posts.task_id')
            ->join('publications', 'publications.id', '=', 'publication_tasks.publication_id')
            ->select(
                'vk_posts.id as vk_post_id',
                'vk_posts.post_id',
                'vk_posts.owner_id',
                'vk_posts.posted_at',
                'offers.code as offer_code',
                'publications.scenario',
            )
            ->orderBy('vk_posts.posted_at', 'desc')
            ->paginate(25);

        $postIds = collect($posts->items())->pluck('vk_post_id')->toArray();
        $stats = $this->loadLatestStats($postIds);

        $posts->getCollection()->transform(function ($post) use ($stats) {
            $postStats = $stats[$post->vk_post_id] ?? null;
            $scenario = $this->resolveScenarioLabel($post->scenario);
            $postUrl = 'https://vk.com/wall' . $post->owner_id . '_' . $post->post_id;

            return (object) [
                'code' => $post->offer_code,
                'scenario' => $scenario,
                'post_url' => $postUrl,
                'posted_at' => $post->posted_at
                    ? date('d.m.Y H:i', strtotime($post->posted_at))
                    : '—',
                'views' => $postStats?->views ?? 0,
                'reposts' => $postStats?->reposts ?? 0,
                'likes' => $postStats?->likes ?? 0,
                'comments' => $postStats?->comments ?? 0,
            ];
        });

        return view('vk-posts-list', compact('posts'));
    }

    /**
     * @param  array<int>  $postIds
     * @return array<int, object>
     */
    private function loadLatestStats(array $postIds): array
    {
        if (empty($postIds)) {
            return [];
        }

        return DB::table('vk_post_stats as ps')
            ->whereIn('ps.vk_post_id', $postIds)
            ->where('ps.datetime', function ($query) {
                $query->selectRaw('MAX(ps2.datetime)')
                    ->from('vk_post_stats as ps2')
                    ->whereColumn('ps2.vk_post_id', 'ps.vk_post_id');
            })
            ->select('ps.vk_post_id', 'ps.views', 'ps.reposts', 'ps.likes', 'ps.comments')
            ->get()
            ->keyBy('vk_post_id')
            ->toArray();
    }

    private function resolveScenarioLabel(?string $scenarioValue): string
    {
        if ($scenarioValue === null) {
            return '—';
        }

        try {
            return ScenarioType::from($scenarioValue)->label();
        } catch (\Throwable) {
            return $scenarioValue;
        }
    }
}
