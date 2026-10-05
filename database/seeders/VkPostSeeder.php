<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class VkPostSeeder extends Seeder
{
    public function run(): void
    {
        $sql = File::get(database_path('seeders/prod.sql'));

        preg_match('/INSERT INTO `vk_posts` \([^)]+\) VALUES[\s\S]*?;/', $sql, $matches);
        if (! empty($matches[0])) {
            DB::unprepared($matches[0]);
        }
    }
}
