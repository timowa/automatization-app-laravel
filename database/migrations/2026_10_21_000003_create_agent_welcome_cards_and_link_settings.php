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
        Schema::create('agent_welcome_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->string('path');
            $table->string('thumb_path');
            $table->unsignedBigInteger('media_id');
            $table->timestamps();

            $table->unique('agent_id');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->foreignId('friend_request_card_id')
                ->nullable()
                ->after('friend_request_welcome_text')
                ->constrained('agent_welcome_cards')
                ->nullOnDelete();
        });

        if (Schema::hasColumn('settings', 'friend_request_card_path')) {
            $rows = DB::table('settings')
                ->whereNotNull('friend_request_card_media_id')
                ->whereNotNull('friend_request_card_path')
                ->get(['id', 'agent_id', 'friend_request_card_path', 'friend_request_card_media_id']);

            foreach ($rows as $row) {
                $path = (string) $row->friend_request_card_path;
                $cardId = DB::table('agent_welcome_cards')->insertGetId([
                    'agent_id' => $row->agent_id,
                    'path' => $path,
                    'thumb_path' => $path,
                    'media_id' => $row->friend_request_card_media_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('settings')->where('id', $row->id)->update([
                    'friend_request_card_id' => $cardId,
                ]);
            }

            Schema::table('settings', function (Blueprint $table) {
                $table->dropColumn(['friend_request_card_path', 'friend_request_card_media_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('friend_request_card_path')->nullable()->after('friend_request_welcome_text');
            $table->unsignedBigInteger('friend_request_card_media_id')->nullable()->after('friend_request_card_path');
        });

        $cards = DB::table('agent_welcome_cards')->get();
        foreach ($cards as $card) {
            DB::table('settings')
                ->where('friend_request_card_id', $card->id)
                ->update([
                    'friend_request_card_path' => $card->path,
                    'friend_request_card_media_id' => $card->media_id,
                ]);
        }

        Schema::table('settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('friend_request_card_id');
        });

        Schema::dropIfExists('agent_welcome_cards');
    }
};
