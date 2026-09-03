<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vk_users', function (Blueprint $table) {
            $table->boolean('is_token_valid')->default(true)->after('is_token_available');
        });
    }

    public function down(): void
    {
        Schema::table('vk_users', function (Blueprint $table) {
            $table->dropColumn('is_token_valid');
        });
    }
};