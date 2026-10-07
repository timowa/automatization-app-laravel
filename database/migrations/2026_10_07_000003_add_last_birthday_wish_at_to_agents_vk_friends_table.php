<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents_vk_friends', function (Blueprint $table) {
            $table->date('last_birthday_wish_at')->nullable()->after('sex')->comment('Дата последнего поздравления с днём рождения');
        });
    }

    public function down(): void
    {
        Schema::table('agents_vk_friends', function (Blueprint $table) {
            $table->dropColumn('last_birthday_wish_at');
        });
    }
};
