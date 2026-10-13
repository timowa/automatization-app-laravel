<?php

declare(strict_types=1);

namespace App\Actions\Vk;

use App\Models\AgentVkFriend;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class ListAgentVkFriendsAction
{
    /**
     * @param  array{
     *     q: string,
     *     sex: int|null,
     *     city: list<string>,
     *     country: string|null,
     *     relation: int|null,
     *     page: string|null,
     *     closed: int|null,
     *     home_town: string|null,
     *     occupation: string|null
     * }  $filters
     * @return Collection<int, AgentVkFriend>
     */
    public function execute(int $agentId, array $filters): Collection
    {
        $query = $this->baseQuery($agentId);

        if ($filters['q'] !== '') {
            $like = '%'.addcslashes($filters['q'], '%_\\').'%';
            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('nickname', 'like', $like)
                    ->orWhere('middle_name', 'like', $like)
                    ->orWhere('maiden_name', 'like', $like)
                    ->orWhere('domain', 'like', $like)
                    ->orWhere('mobile_phone', 'like', $like)
                    ->orWhere('home_phone', 'like', $like)
                    ->orWhere('home_town', 'like', $like)
                    ->orWhere('occupation_name', 'like', $like)
                    ->orWhere('relation_partner_name', 'like', $like)
                    ->orWhere('friend_list_names', 'like', $like)
                    ->orWhere('site', 'like', $like)
                    ->orWhere('status', 'like', $like);
            });
        }

        if ($filters['sex'] !== null) {
            $query->where('sex', $filters['sex']);
        }

        if ($filters['city'] !== []) {
            $query->whereIn('city_name', $filters['city']);
        }

        if ($filters['country'] !== null) {
            $query->where('country_name', $filters['country']);
        }

        if ($filters['relation'] !== null) {
            $query->where('relation', $filters['relation']);
        }

        if ($filters['page'] === 'active') {
            $query->whereNull('deactivated');
        } elseif ($filters['page'] !== null) {
            $query->where('deactivated', $filters['page']);
        }

        if ($filters['closed'] !== null) {
            $query->where('is_closed', $filters['closed']);
        }

        if ($filters['home_town'] !== null) {
            $query->where('home_town', $filters['home_town']);
        }

        if ($filters['occupation'] !== null) {
            $query->where('occupation_type', $filters['occupation']);
        }

        return $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('user_id')
            ->get();
    }

    /**
     * @return Collection<int, string>
     */
    public function cities(int $agentId): Collection
    {
        return $this->distinctLabels($agentId, 'city_name');
    }

    /**
     * @return Collection<int, string>
     */
    public function countries(int $agentId): Collection
    {
        return $this->distinctLabels($agentId, 'country_name');
    }

    /**
     * @return Collection<int, string>
     */
    public function hometowns(int $agentId): Collection
    {
        return $this->distinctLabels($agentId, 'home_town');
    }

    /**
     * @return Collection<int, string>
     */
    private function distinctLabels(int $agentId, string $column): Collection
    {
        return $this->baseQuery($agentId)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->map(fn (mixed $value): string => (string) $value)
            ->values();
    }

    /**
     * @return Builder<AgentVkFriend>
     */
    private function baseQuery(int $agentId): Builder
    {
        return AgentVkFriend::query()->where('agent_id', $agentId);
    }
}
