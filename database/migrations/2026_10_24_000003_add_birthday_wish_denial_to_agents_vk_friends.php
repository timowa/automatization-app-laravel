<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents_vk_friends', function (Blueprint $table) {
            $table->date('birthday_wish_denied_at')->nullable()->after('last_birthday_wish_at');
            $table->char('birthday_wish_denied_token_hash', 64)->nullable()->after('birthday_wish_denied_at');
        });
    }

    public function down(): void
    {
        Schema::table('agents_vk_friends', function (Blueprint $table) {
            $table->dropColumn(['birthday_wish_denied_at', 'birthday_wish_denied_token_hash']);
        });
    }
};
