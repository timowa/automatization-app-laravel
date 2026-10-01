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
Schedule::command('vk:sync-friends')->dailyAt('02:00');
Schedule::command('vk:dispatch-birthday-wishes')->dailyAt('04:00');
Schedule::command('vk:publish-loop-stories')->cron('0 3 */3 * *');
Schedule::command('vk:get-stats')->everyTwoHours();

