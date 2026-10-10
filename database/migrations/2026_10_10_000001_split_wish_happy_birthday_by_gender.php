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
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('wish_happy_birthday_male')->default(false)->after('wish_happy_birthday');
            $table->boolean('wish_happy_birthday_female')->default(false)->after('wish_happy_birthday_male');
        });

        DB::table('settings')->orderBy('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                $enabled = (bool) $row->wish_happy_birthday;

                DB::table('settings')
                    ->where('id', $row->id)
                    ->update([
                        'wish_happy_birthday_male' => $enabled,
                        'wish_happy_birthday_female' => $enabled,
                    ]);
            }
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('wish_happy_birthday');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('wish_happy_birthday')->default(false)->after('agent_id');
        });

        DB::table('settings')->orderBy('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('settings')
                    ->where('id', $row->id)
                    ->update([
                        'wish_happy_birthday' => (bool) $row->wish_happy_birthday_male
                            || (bool) $row->wish_happy_birthday_female,
                    ]);
            }
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['wish_happy_birthday_male', 'wish_happy_birthday_female']);
        });
    }
};
