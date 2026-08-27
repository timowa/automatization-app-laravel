<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vk_posts', function (Blueprint $table) {
            $table->id();
            $table->integer('offer_id')->notNullable();
            $table->integer('post_id')->notNullable();
            $table->integer('task_id')->notNullable();
            $table->integer('owner_id')->notNullable();
            $table->dateTime('posted_at')->default(now());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vk_posts');
    }
};
