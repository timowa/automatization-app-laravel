<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TruncateCommand extends Command
{
    protected $signature = 'app:truncate';
    protected $description = 'Очистка таблиц offers, vk_posts, jobs';

    public function handle(): int
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::statement('TRUNCATE TABLE offers');
        DB::statement('TRUNCATE TABLE vk_posts');
        DB::statement('TRUNCATE TABLE jobs');
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->info('Таблицы offers, vk_posts, jobs очищены');
        return self::SUCCESS;
    }
}
