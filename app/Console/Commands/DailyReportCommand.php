<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Vk\VkApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DailyReportCommand extends Command
{
    protected $signature = 'vk:daily-report';
    protected $description = 'Ежедневный отчёт о работе системы';

    public function handle(VkApiService $vkApi): int
    {
        $offersCount = DB::table('offers')->where('created_at', '>=', now()->subDay())->count();
        $postsCount = DB::table('vk_posts')->where('posted_at', '>=', now()->subDay())->count();
        $errorsCount = DB::table('publication_tasks')->where('status', 'failed')->where('updated_at', '>=', now()->subDay())->count();

        $text = "Отчёт за " . now()->format('d.m.Y') . "\n"
            . "Создано офферов: {$offersCount}\n"
            . "Опубликовано постов: {$postsCount}\n"
            . "Ошибок в задачах: {$errorsCount}";

        try {
            $vkApi->sendTechMessage($text);
            Log::channel('job')->info('Ежедневный отчёт отправлен');
        } catch (\Throwable $e) {
            Log::channel('vk')->error($e->getMessage());
            Log::channel('job')->warning('Ошибка отправки ежедневного отчёта');
        }

        $this->info('Ежедневный отчёт отправлен');
        return self::SUCCESS;
    }
}