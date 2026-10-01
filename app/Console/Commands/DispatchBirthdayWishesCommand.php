<?php

declare(strict_types=1);

namespace App\Console\Commands;

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
            ->where(function ($query) use ($monthDay): void {
                $query
                    ->where('agents_vk_friends.bdate', $monthDay)
                    ->orWhere('agents_vk_friends.bdate', 'like', '%-'.$monthDay);
            })
            ->orderBy('agents_vk_friends.agent_id')
            ->orderBy('agents_vk_friends.user_id')
            ->select([
                'agents_vk_friends.agent_id',
                'agents_vk_friends.user_id',
            ])
            ->get();

        Log::channel('job')->info('Количество поздравлений на сегодня', [
            'count' => $birthdayFriends->count(),
        ]);

        foreach ($birthdayFriends as $index => $friend) {
            WishHappyBirthdayJob::dispatch(
                (int) $friend->agent_id,
                (int) $friend->user_id
            )->delay(now()->addMinutes($index * 10));
        }

        $this->info('Поздравлений запланировано: '.$birthdayFriends->count());

        return self::SUCCESS;
    }
}
