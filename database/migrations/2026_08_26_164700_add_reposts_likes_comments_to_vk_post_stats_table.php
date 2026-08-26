<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vk_post_stats', function (Blueprint $table) {
            $table->integer('reposts')->notNullable()->default(0)->after('views');
            $table->integer('likes')->notNullable()->default(0)->after('reposts');
            $table->integer('comments')->notNullable()->default(0)->after('likes');
        });
    }

    public function down(): void
    {
        Schema::table('vk_post_stats', function (Blueprint $table) {
            $table->dropColumn(['reposts', 'likes', 'comments']);
        });
    }
};
