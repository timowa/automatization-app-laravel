<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_vk_friend_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->boolean('can_access_closed')->default(true);
            $table->string('deactivated')->nullable();
            $table->unsignedInteger('mutual_count')->default(0);
            $table->string('track_code', 512)->nullable();
            $table->json('payload')->nullable();
            $table->unsignedBigInteger('message_id')->nullable();
            $table->timestamps();

            $table->unique(['agent_id', 'user_id']);
            $table->index(['message_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_vk_friend_requests');
    }
};
