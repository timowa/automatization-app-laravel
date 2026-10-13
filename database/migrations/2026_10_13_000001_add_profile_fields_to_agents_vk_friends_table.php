<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents_vk_friends', function (Blueprint $table) {
            $table->string('nickname', 255)->nullable()->comment('Никнейм');
            $table->string('domain', 255)->nullable()->comment('Короткий адрес страницы');
            $table->unsignedInteger('city_id')->nullable()->comment('ID города');
            $table->string('city_name', 255)->nullable()->comment('Город');
            $table->unsignedInteger('country_id')->nullable()->comment('ID страны');
            $table->string('country_name', 255)->nullable()->comment('Страна');
            $table->string('mobile_phone', 64)->nullable()->comment('Мобильный телефон');
            $table->string('home_phone', 64)->nullable()->comment('Домашний телефон');
            $table->unsignedInteger('university_id')->nullable()->comment('ID вуза');
            $table->string('university_name', 255)->nullable()->comment('Вуз');
            $table->unsignedInteger('faculty_id')->nullable()->comment('ID факультета');
            $table->string('faculty_name', 255)->nullable()->comment('Факультет');
            $table->unsignedSmallInteger('graduation')->nullable()->comment('Год выпуска');
            $table->boolean('has_mobile')->nullable()->comment('Указан мобильный телефон');
            $table->smallInteger('timezone')->nullable()->comment('Часовой пояс, смещение от UTC');
            $table->unsignedInteger('last_seen_at')->nullable()->comment('Время последнего посещения');
            $table->unsignedTinyInteger('last_seen_platform')->nullable()->comment('Платформа последнего посещения');
            $table->boolean('online')->nullable()->comment('Сейчас в сети');
            $table->text('photo_50')->nullable()->comment('Фото 50');
            $table->text('photo_100')->nullable()->comment('Фото 100');
            $table->text('photo_200_orig')->nullable()->comment('Фото 200');
            $table->string('photo_id', 64)->nullable()->comment('ID фото');
            $table->unsignedTinyInteger('relation')->nullable()->comment('Семейное положение');
            $table->text('status')->nullable()->comment('Статус');
            $table->json('universities')->nullable()->comment('Университеты');
        });

        DB::table('agents_vk_friends')
            ->whereNull('nickname')
            ->whereNotNull('middle_name')
            ->update(['nickname' => DB::raw('middle_name')]);
    }

    public function down(): void
    {
        Schema::table('agents_vk_friends', function (Blueprint $table) {
            $table->dropColumn([
                'nickname',
                'domain',
                'city_id',
                'city_name',
                'country_id',
                'country_name',
                'mobile_phone',
                'home_phone',
                'university_id',
                'university_name',
                'faculty_id',
                'faculty_name',
                'graduation',
                'has_mobile',
                'timezone',
                'last_seen_at',
                'last_seen_platform',
                'online',
                'photo_50',
                'photo_100',
                'photo_200_orig',
                'photo_id',
                'relation',
                'status',
                'universities',
            ]);
        });
    }
};
