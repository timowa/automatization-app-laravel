<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class VkPostStatSeeder extends Seeder
{
    public function run(): void
    {
        $sql = File::get(database_path('seeders/_seed_inserts.sql'));

        preg_match('/INSERT INTO `vk_post_stats` \([^)]+\) VALUES[\s\S]*?;/', $sql, $matches);
        if (!empty($matches[0])) {
            DB::unprepared($matches[0]);
        }

        DB::statement('ALTER TABLE `vk_post_stats` AUTO_INCREMENT = 9');
    }
}
