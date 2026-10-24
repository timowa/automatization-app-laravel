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
        Schema::create('changelog_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('version');
            $table->timestamp('notified_at');
        });

        DB::table('changelog_notifications')->insert([
            'version' => (string) config('changelog.current', '1.0'),
            'notified_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('changelog_notifications');
    }
};
