<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vk_users', function (Blueprint $table) {
            $table->id();
            $table->string('agent_id', 20)->notNullable();
            $table->string('vk_user_id', 255)->notNullable();
            $table->text('vk_token')->notNullable();
            $table->string('email', 255)->nullable();
            $table->integer('is_token_available')->default(1);
            $table->string('first_name', 100)->nullable()->comment('Имя');
            $table->string('last_name', 100)->nullable()->comment('Фамилия');
            $table->string('screen_name', 255)->nullable()->comment('Короткое имя страницы');
            $table->string('domain', 255)->nullable()->comment('Короткий адрес страницы');
            $table->enum('deactivated', ['deleted', 'banned'])->nullable()->comment('Страница удалена или заблокирована');
            $table->tinyInteger('is_closed')->default(0)->comment('Профиль закрыт');
            $table->tinyInteger('can_access_closed')->default(0)->comment('Есть доступ к закрытому профилю');
            $table->tinyInteger('sex')->unsigned()->nullable()->comment('Пол (0 - не указан, 1 - женский, 2 - мужской)');
            $table->string('bdate', 10)->nullable()->comment('Дата рождения');
            $table->tinyInteger('relation')->unsigned()->nullable()->comment('Семейное положение');
            $table->string('home_town', 255)->nullable()->comment('Родной город');
            $table->integer('city_id')->unsigned()->nullable()->comment('ID текущего города');
            $table->string('city_name', 255)->nullable()->comment('Название текущего города');
            $table->integer('country_id')->unsigned()->nullable()->comment('ID страны');
            $table->string('country_name', 255)->nullable()->comment('Название страны');
            $table->tinyInteger('online')->default(0)->comment('Пользователь онлайн');
            $table->integer('last_seen_at')->unsigned()->nullable()->comment('Unix-время последнего посещения');
            $table->tinyInteger('last_seen_platform')->unsigned()->nullable()->comment('Платформа последнего посещения');
            $table->integer('followers_count')->unsigned()->nullable()->comment('Количество подписчиков');
            $table->tinyInteger('friend_status')->unsigned()->nullable()->comment('Статус дружбы');
            $table->text('status')->nullable()->comment('Статус пользователя');
            $table->tinyInteger('verified')->default(0)->comment('Верифицированный аккаунт');
            $table->json('raw')->nullable()->comment('Полный JSON объекта пользователя VK');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vk_users');
    }
};
