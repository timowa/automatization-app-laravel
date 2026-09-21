<?php

namespace App\Actions;

use Illuminate\Support\Facades\DB;

class GetStatsAction
{
    public function execute(string $offerId): array
    {
        $rows = DB::table('vk_posts as vp')
            ->join('offers as o', 'o.id', '=', 'vp.offer_id')
            ->leftJoin('vk_post_stats as ps', function ($join) {
                $join->on('ps.vk_post_id', '=', 'vp.id')
                    ->where('ps.datetime', function ($query) {
                        $query->selectRaw('MAX(ps2.datetime)')
                            ->from('vk_post_stats as ps2')
                            ->whereColumn('ps2.vk_post_id', 'ps.vk_post_id');
                    });
            })
            ->where('o.offer_id', $offerId)
            ->select(
                'vp.owner_id',
                'vp.post_id',
                'ps.views',
                'ps.reposts',
                'ps.likes',
                'ps.comments',
            )
            ->get();

        return $rows->map(fn ($row) => [
            'vk_post_id' => $row->owner_id . '_' . $row->post_id,
            'total' => [
                'likes' => $row->likes ?? 0,
                'views' => $row->views ?? 0,
                'comments' => $row->comments ?? 0,
                'reposts' => $row->reposts ?? 0,
            ],
        ])->all();
    }
}
