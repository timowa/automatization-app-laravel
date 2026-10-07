<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Vk\Sex;
use App\Helpers\BirthdayWishEligibility;
use App\Jobs\WishHappyBirthdayJob;
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
            ->where('settings.wish_happy_birthday', true)
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
                            ->whereNotNull('settings.birthday_wish_male_text')
                            ->where('settings.birthday_wish_male_text', '!=', '');
                    })
                    ->orWhere(function ($femaleQuery): void {
                        $femaleQuery
                            ->where('agents_vk_friends.sex', Sex::FEMALE->value)
                            ->whereNotNull('settings.birthday_wish_female_text')
                            ->where('settings.birthday_wish_female_text', '!=', '');
                    });
            })
            ->orderBy('agents_vk_friends.agent_id')
            ->orderBy('agents_vk_friends.user_id')
            ->select([
                'agents_vk_friends.agent_id',
                'agents_vk_friends.user_id',
                'agents_vk_friends.bdate',
                'agents_vk_friends.last_birthday_wish_at',
            ])
            ->get();

        $eligible = $birthdayFriends
            ->filter(function (object $friend): bool {
                return BirthdayWishEligibility::ageInRange($friend->bdate)
                    && ! BirthdayWishEligibility::wasWishedThisYear($friend->last_birthday_wish_at);
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
}
