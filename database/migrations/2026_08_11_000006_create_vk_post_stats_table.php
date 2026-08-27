<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vk_post_stats', function (Blueprint $table) {
            $table->id();
            $table->integer('vk_post_id')->notNullable();
            $table->integer('views')->notNullable();
            $table->dateTime('datetime')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vk_post_stats');
    }
};
