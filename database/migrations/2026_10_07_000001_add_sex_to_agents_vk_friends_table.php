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
            $table->unsignedTinyInteger('sex')->nullable()->after('middle_name')->comment('Пол VK: 1 — женский, 2 — мужской');
        });
    }

    public function down(): void
    {
        Schema::table('agents_vk_friends', function (Blueprint $table) {
            $table->dropColumn('sex');
        });
    }
};
