<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->integer('offer_id')->notNullable();
            $table->string('code')->notNullable();
            $table->integer('stage')->notNullable();
            $table->integer('status')->notNullable();
            $table->integer('price')->notNullable();
            $table->decimal('area', 8, 2)->notNullable();
            $table->decimal('kitchen_area', 8, 2)->nullable();
            $table->decimal('living_area', 8, 2)->nullable();
            $table->integer('city')->notNullable();
            $table->json('location')->nullable();
            $table->integer('agent_id')->notNullable();
            $table->json('images')->notNullable();
            $table->integer('deal')->notNullable();
            $table->integer('category')->notNullable();
            $table->integer('rooms')->notNullable();
            $table->integer('rooms_offered')->nullable();
            $table->integer('floor')->nullable();
            $table->integer('floors_total')->nullable();
            $table->integer('commission')->nullable();
            $table->integer('deposit')->nullable();
            $table->dateTime('created_at')->default(now());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
