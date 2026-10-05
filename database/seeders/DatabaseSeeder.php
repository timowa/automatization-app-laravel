<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AgentSeeder::class,
            SettingSeeder::class,
            OfferSeeder::class,
            OfferImageSeeder::class,
            VkUserSeeder::class,
            VkGroupSeeder::class,
            PublicationSeeder::class,
            PublicationTaskSeeder::class,
            VkLoopStorySeeder::class,
            VkPostSeeder::class,
            VkPostStatSeeder::class,
        ]);
    }
}
