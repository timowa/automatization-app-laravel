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
            $table->string('deactivated', 16)->nullable()->comment('Страница удалена или заблокирована: deleted, banned');
            $table->boolean('is_closed')->nullable()->comment('Профиль закрыт');
            $table->boolean('has_photo')->nullable()->comment('Установлено фото профиля');
            $table->string('home_town', 255)->nullable()->comment('Родной город');
            $table->string('occupation_type', 32)->nullable()->comment('Род занятий: work, school, university');
            $table->string('occupation_name', 255)->nullable()->comment('Место работы или учёбы');
            $table->string('education_form', 255)->nullable()->comment('Форма обучения');
            $table->string('education_status', 255)->nullable()->comment('Статус учёбы');
            $table->boolean('blacklisted_by_me')->nullable()->comment('Пользователь в чёрном списке агента');
            $table->unsignedInteger('followers_count')->nullable()->comment('Количество подписчиков');
            $table->boolean('verified')->nullable()->comment('Страница подтверждена');
            $table->string('maiden_name', 100)->nullable()->comment('Девичья фамилия');
            $table->unsignedBigInteger('relation_partner_id')->nullable()->comment('ID партнёра');
            $table->string('relation_partner_name', 255)->nullable()->comment('Имя партнёра');
            $table->json('schools')->nullable()->comment('Школы');
            $table->json('career')->nullable()->comment('Карьера');
            $table->string('friend_list_names', 500)->nullable()->comment('Названия списков друзей');
            $table->string('site', 255)->nullable()->comment('Сайт');
        });
    }

    public function down(): void
    {
        Schema::table('agents_vk_friends', function (Blueprint $table) {
            $table->dropColumn([
                'deactivated',
                'is_closed',
                'has_photo',
                'home_town',
                'occupation_type',
                'occupation_name',
                'education_form',
                'education_status',
                'blacklisted_by_me',
                'followers_count',
                'verified',
                'maiden_name',
                'relation_partner_id',
                'relation_partner_name',
                'schools',
                'career',
                'friend_list_names',
                'site',
            ]);
        });
    }
};
