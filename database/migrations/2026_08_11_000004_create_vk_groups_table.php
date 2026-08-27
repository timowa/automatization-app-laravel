<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vk_groups', function (Blueprint $table) {
            $table->id();
            $table->integer('group_id')->notNullable();
            $table->integer('city')->notNullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vk_groups');
    }
};
