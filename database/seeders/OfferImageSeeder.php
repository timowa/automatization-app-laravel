<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class OfferImageSeeder extends Seeder
{
    public function run(): void
    {
        $sql = File::get(database_path('seeders/prod.sql'));

        preg_match_all('/INSERT INTO `offer_images` \([^)]+\) VALUES[\s\S]*?;/', $sql, $matches);
        foreach ($matches[0] as $statement) {
            DB::unprepared($statement);
        }
    }
}
