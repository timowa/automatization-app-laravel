<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('vk:check-tokens')->daily();
Schedule::command('vk:daily-report')->dailyAt('07:00');
Schedule::command('vk:sync-users')->daily();
Schedule::command('vk:publish-loop-stories')->cron('0 3 */3 * *');
Schedule::command('vk:get-stats')->everyTwoHours();

