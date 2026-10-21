<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Бывшие значения City::CHIKAGO (3) и City::UST_ABAKAN (4)
     * сведены к City::ABAKAN (1).
     */
    public function up(): void
    {
        DB::table('offers')->whereIn('city', [3, 4])->update(['city' => 1]);
        DB::table('vk_groups')->whereIn('city', [3, 4])->update(['city' => 1]);
    }

    public function down(): void
    {
        // Обратный маппинг невозможен: алиасы уже сведены к Абакану.
    }
};
