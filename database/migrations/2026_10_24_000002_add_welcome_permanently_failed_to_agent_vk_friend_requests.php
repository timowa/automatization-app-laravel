<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_vk_friend_requests', function (Blueprint $table) {
            $table->boolean('welcome_permanently_failed')
                ->default(false)
                ->after('message_id');
            $table->index(['welcome_permanently_failed', 'message_id', 'id'], 'friend_requests_welcome_queue_idx');
        });
    }

    public function down(): void
    {
        Schema::table('agent_vk_friend_requests', function (Blueprint $table) {
            $table->dropIndex('friend_requests_welcome_queue_idx');
            $table->dropColumn('welcome_permanently_failed');
        });
    }
};
