<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('friend_request_welcome_enabled')->default(false)->after('birthday_wish_female_text');
            $table->text('friend_request_welcome_text')->nullable()->after('friend_request_welcome_enabled');
            $table->string('friend_request_card_path')->nullable()->after('friend_request_welcome_text');
            $table->unsignedBigInteger('friend_request_card_media_id')->nullable()->after('friend_request_card_path');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'friend_request_welcome_enabled',
                'friend_request_welcome_text',
                'friend_request_card_path',
                'friend_request_card_media_id',
            ]);
        });
    }
};
