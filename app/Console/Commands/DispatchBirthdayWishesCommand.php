<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Vk\Sex;
use App\Helpers\BirthdayWishEligibility;
use App\Jobs\WishHappyBirthdayJob;
use App\Models\VkUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DispatchBirthdayWishesCommand extends Command
{
    protected $signature = 'vk:dispatch-birthday-wishes';

    protected $description = 'Постановка в очередь поздравлений друзей VK с днём рождения';

    public function handle(): int
    {
        $monthDay = now()->format('m-d');

        $birthdayFriends = DB::table('agents_vk_friends')
            ->join('settings', 'settings.agent_id', '=', 'agents_vk_friends.agent_id')
            ->whereIn('agents_vk_friends.sex', [Sex::FEMALE->value, Sex::MALE->value])
            ->where('agents_vk_friends.bdate', 'like', '____-'.$monthDay)
            ->where(function ($query): void {
                $query
                    ->whereNull('agents_vk_friends.last_birthday_wish_at')
                    ->orWhereYear('agents_vk_friends.last_birthday_wish_at', '<', (int) now()->format('Y'));
            })
            ->where(function ($query): void {
                $query
                    ->where(function ($maleQuery): void {
                        $maleQuery
                            ->where('agents_vk_friends.sex', Sex::MALE->value)
                            ->where('settings.wish_happy_birthday_male', true);
                    })
                    ->orWhere(function ($femaleQuery): void {
                        $femaleQuery
                            ->where('agents_vk_friends.sex', Sex::FEMALE->value)
                            ->where('settings.wish_happy_birthday_female', true);
                    });
            })
            ->orderBy('agents_vk_friends.agent_id')
            ->orderBy('agents_vk_friends.user_id')
            ->select([
                'agents_vk_friends.agent_id',
                'agents_vk_friends.user_id',
                'agents_vk_friends.bdate',
                'agents_vk_friends.last_birthday_wish_at',
                'agents_vk_friends.birthday_wish_denied_at',
                'agents_vk_friends.birthday_wish_denied_token_hash',
            ])
            ->get();

        $tokenHashes = $this->tokenHashes(
            $birthdayFriends->pluck('agent_id')->map(fn ($id): int => (int) $id)->unique()->all()
        );

        $eligible = $birthdayFriends
            ->filter(function (object $friend) use ($tokenHashes): bool {
                $hash = $tokenHashes[(int) $friend->agent_id] ?? '';

                return BirthdayWishEligibility::ageInRange($friend->bdate)
                    && ! BirthdayWishEligibility::wasWishedThisYear($friend->last_birthday_wish_at)
                    && ! BirthdayWishEligibility::isDeniedForCurrentToken(
                        $friend->birthday_wish_denied_at,
                        is_string($friend->birthday_wish_denied_token_hash) ? $friend->birthday_wish_denied_token_hash : null,
                        $hash,
                    );
            })
            ->values();

        Log::channel('job')->info('Количество поздравлений на сегодня', [
            'count' => $eligible->count(),
        ]);

        foreach ($eligible as $index => $friend) {
            WishHappyBirthdayJob::dispatch(
                (int) $friend->agent_id,
                (int) $friend->user_id
            )->delay(now()->addMinutes($index * 10));
        }

        $this->info('Поздравлений запланировано: '.$eligible->count());

        return self::SUCCESS;
    }

    /**
     * @param  list<int>  $agentIds
     * @return array<int, string>
     */
    private function tokenHashes(array $agentIds): array
    {
        if ($agentIds === []) {
            return [];
        }

        $hashes = [];
        $users = VkUser::query()
            ->whereIn('agent_id', $agentIds)
            ->get(['agent_id', 'vk_token']);

        foreach ($users as $user) {
            $hashes[(int) $user->agent_id] = BirthdayWishEligibility::tokenHash($user->getToken());
        }

        return $hashes;
    }
}
