<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('vk:check-tokens')->daily();
Schedule::command('vk:daily-report')->dailyAt('07:00');
Schedule::command('vk:sync-users')->daily();
Schedule::command('vk:sync-friends')->weeklyOn(1, '02:00');
Schedule::command('vk:dispatch-birthday-wishes')->dailyAt('04:00');
Schedule::command('vk:publish-loop-stories')->dailyAt('10:00');
Schedule::command('vk:get-stats')->everyTwoHours();
Schedule::command('vk:sync-friend-requests')->cron('0 0,6,12,18 * * *');
Schedule::command('vk:process-friend-request-welcome')->everyFiveMinutes();
