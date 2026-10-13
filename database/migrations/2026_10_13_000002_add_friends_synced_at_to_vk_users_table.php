<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vk_users', function (Blueprint $table) {
            $table->timestamp('friends_synced_at')->nullable()->comment('Дата последней синхронизации списка друзей');
        });
    }

    public function down(): void
    {
        Schema::table('vk_users', function (Blueprint $table) {
            $table->dropColumn('friends_synced_at');
        });
    }
};
