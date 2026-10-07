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
            $table->text('birthday_wish_male_text')->nullable()->after('wish_happy_birthday');
            $table->text('birthday_wish_female_text')->nullable()->after('birthday_wish_male_text');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['birthday_wish_male_text', 'birthday_wish_female_text']);
        });
    }
};
