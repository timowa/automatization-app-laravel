<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents_vk_friends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('bdate', 10)->nullable();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->timestamps();

            $table->unique(['agent_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents_vk_friends');
    }
};
