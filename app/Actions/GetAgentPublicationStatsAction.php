<?php

declare(strict_types=1);

namespace App\Actions;

use App\Scenarios\ScenarioFactory;
use Illuminate\Support\Facades\DB;

class GetAgentPublicationStatsAction
{
    public function __construct(private readonly ScenarioFactory $scenarioFactory)
    {
    }

    /**
     * @return array{
     *     statsByOffer: array<string, array{
     *         total: array{views: int, reposts: int, likes: int, comments: int},
     *         scenarios: array<string, array{views: int, reposts: int, likes: int, comments: int}>
     *     }>,
     *     scenarioOrder: list<string>
     * }
     */
    public function execute(int $agentId): array
    {
        return [
            'statsByOffer' => $this->statsByOffer($agentId),
            'scenarioOrder' => $this->scenarioOrder(),
        ];
    }

    /**
     * @return array<string, array{
     *     total: array{views: int, reposts: int, likes: int, comments: int},
     *     scenarios: array<string, array{views: int, reposts: int, likes: int, comments: int}>
     * }>
     */
    private function statsByOffer(int $agentId): array
    {
        $latestStatIds = DB::table('vk_post_stats')
            ->selectRaw('MAX(id) as id')
            ->whereIn('vk_post_id', function ($query) use ($agentId): void {
                $query->select('vk_posts.id')
                    ->from('vk_posts')
                    ->join('offers', 'offers.id', '=', 'vk_posts.offer_id')
                    ->where('offers.agent_id', $agentId);
            })
            ->groupBy('vk_post_id');

        $latestStats = DB::table('vk_post_stats as ps')
            ->joinSub($latestStatIds, 'latest', 'latest.id', '=', 'ps.id')
            ->join('vk_posts as vp', 'vp.id', '=', 'ps.vk_post_id')
            ->join('publication_tasks as pt', 'pt.id', '=', 'vp.task_id')
            ->join('publications as p', 'p.id', '=', 'pt.publication_id')
            ->join('offers as o', 'o.id', '=', 'vp.offer_id')
            ->select(
                'o.code',
                'p.scenario',
                'ps.views',
                'ps.reposts',
                'ps.likes',
                'ps.comments',
            )
            ->get();

        $statsByOffer = [];
        foreach ($latestStats as $row) {
            $code = $row->code;
            if (! isset($statsByOffer[$code])) {
                $statsByOffer[$code] = [
                    'total' => ['views' => 0, 'reposts' => 0, 'likes' => 0, 'comments' => 0],
                    'scenarios' => [],
                ];
            }

            $statsByOffer[$code]['total']['views'] += (int) $row->views;
            $statsByOffer[$code]['total']['reposts'] += (int) $row->reposts;
            $statsByOffer[$code]['total']['likes'] += (int) $row->likes;
            $statsByOffer[$code]['total']['comments'] += (int) $row->comments;

            $scenario = $row->scenario;
            if (! isset($statsByOffer[$code]['scenarios'][$scenario])) {
                $statsByOffer[$code]['scenarios'][$scenario] = [
                    'views' => 0, 'reposts' => 0, 'likes' => 0, 'comments' => 0,
                ];
            }

            $statsByOffer[$code]['scenarios'][$scenario]['views'] += (int) $row->views;
            $statsByOffer[$code]['scenarios'][$scenario]['reposts'] += (int) $row->reposts;
            $statsByOffer[$code]['scenarios'][$scenario]['likes'] += (int) $row->likes;
            $statsByOffer[$code]['scenarios'][$scenario]['comments'] += (int) $row->comments;
        }

        return $statsByOffer;
    }

    /**
     * @return list<string>
     */
    private function scenarioOrder(): array
    {
        return collect($this->scenarioFactory->list())
            ->map(fn ($class) => (new $class)->type()->value)
            ->values()
            ->all();
    }
}
