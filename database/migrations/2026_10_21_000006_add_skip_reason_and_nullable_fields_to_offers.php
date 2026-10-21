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
        Schema::table('offers', function (Blueprint $table): void {
            $table->string('skip_reason')->nullable()->after('deposit');
            $table->json('payload')->nullable()->after('skip_reason');
        });

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `offers` MODIFY `offer_id` INT NULL');
            DB::statement('ALTER TABLE `offers` MODIFY `stage` INT NULL');
            DB::statement('ALTER TABLE `offers` MODIFY `status` INT NULL');
            DB::statement('ALTER TABLE `offers` MODIFY `price` INT NULL');
            DB::statement('ALTER TABLE `offers` MODIFY `area` DECIMAL(8,2) NULL');
            DB::statement('ALTER TABLE `offers` MODIFY `city` INT NULL');
            DB::statement('ALTER TABLE `offers` MODIFY `agent_id` INT NULL');
            DB::statement('ALTER TABLE `offers` MODIFY `deal` INT NULL');
            DB::statement('ALTER TABLE `offers` MODIFY `category` INT NULL');
            DB::statement('ALTER TABLE `offers` MODIFY `rooms` INT NULL');

            return;
        }

        if ($driver === 'sqlite') {
            $this->rebuildSqliteOffersAsNullable();
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `offers` MODIFY `offer_id` INT NOT NULL');
            DB::statement('ALTER TABLE `offers` MODIFY `stage` INT NOT NULL');
            DB::statement('ALTER TABLE `offers` MODIFY `status` INT NOT NULL');
            DB::statement('ALTER TABLE `offers` MODIFY `price` INT NOT NULL');
            DB::statement('ALTER TABLE `offers` MODIFY `area` DECIMAL(8,2) NOT NULL');
            DB::statement('ALTER TABLE `offers` MODIFY `city` INT NOT NULL');
            DB::statement('ALTER TABLE `offers` MODIFY `agent_id` INT NOT NULL');
            DB::statement('ALTER TABLE `offers` MODIFY `deal` INT NOT NULL');
            DB::statement('ALTER TABLE `offers` MODIFY `category` INT NOT NULL');
            DB::statement('ALTER TABLE `offers` MODIFY `rooms` INT NOT NULL');
        }

        Schema::table('offers', function (Blueprint $table): void {
            $table->dropColumn(['skip_reason', 'payload']);
        });
    }

    /**
     * SQLite не умеет MODIFY COLUMN — пересоздаём таблицу для тестов.
     */
    private function rebuildSqliteOffersAsNullable(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        Schema::create('offers_tmp_nullable', function (Blueprint $table): void {
            $table->id();
            $table->integer('offer_id')->nullable();
            $table->string('code');
            $table->integer('stage')->nullable();
            $table->integer('status')->nullable();
            $table->integer('price')->nullable();
            $table->decimal('area', 8, 2)->nullable();
            $table->decimal('kitchen_area', 8, 2)->nullable();
            $table->decimal('living_area', 8, 2)->nullable();
            $table->integer('city')->nullable();
            $table->json('location')->nullable();
            $table->integer('agent_id')->nullable();
            $table->integer('deal')->nullable();
            $table->integer('category')->nullable();
            $table->integer('rooms')->nullable();
            $table->integer('rooms_offered')->nullable();
            $table->integer('floor')->nullable();
            $table->integer('floors_total')->nullable();
            $table->integer('commission')->nullable();
            $table->integer('deposit')->nullable();
            $table->string('skip_reason')->nullable();
            $table->json('payload')->nullable();
            $table->dateTime('created_at')->nullable();
        });

        $columns = [
            'id',
            'offer_id',
            'code',
            'stage',
            'status',
            'price',
            'area',
            'kitchen_area',
            'living_area',
            'city',
            'location',
            'agent_id',
            'deal',
            'category',
            'rooms',
            'rooms_offered',
            'floor',
            'floors_total',
            'commission',
            'deposit',
            'skip_reason',
            'payload',
            'created_at',
        ];

        $columnList = implode(', ', $columns);
        DB::statement("INSERT INTO offers_tmp_nullable ({$columnList}) SELECT {$columnList} FROM offers");

        Schema::drop('offers');
        Schema::rename('offers_tmp_nullable', 'offers');

        DB::statement('PRAGMA foreign_keys = ON');
    }
};
