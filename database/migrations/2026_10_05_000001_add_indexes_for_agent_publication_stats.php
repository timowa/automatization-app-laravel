<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table): void {
            $table->index('agent_id');
        });

        Schema::table('vk_posts', function (Blueprint $table): void {
            $table->index('offer_id');
        });

        Schema::table('vk_post_stats', function (Blueprint $table): void {
            $table->index('vk_post_id');
        });
    }

    public function down(): void
    {
        Schema::table('vk_post_stats', function (Blueprint $table): void {
            $table->dropIndex(['vk_post_id']);
        });

        Schema::table('vk_posts', function (Blueprint $table): void {
            $table->dropIndex(['offer_id']);
        });

        Schema::table('offers', function (Blueprint $table): void {
            $table->dropIndex(['agent_id']);
        });
    }
};
