<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table): void {
            $table->boolean('exclusive')->default(false);
            $table->boolean('exclusive_transition')->default(false);
            $table->string('exclusive_skip_reason')->nullable();
            $table->index(['city', 'agent_id']);
        });
        Schema::create('exclusive_object_locks', function (Blueprint $table): void {
            $table->string('code')->primary();
        });
        Schema::table('publication_tasks', function (Blueprint $table): void {
            $table->timestamp('external_started_at')->nullable();
        });
        Schema::create('exclusive_publication_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('offer_id')->constrained('offers');
            $table->string('code')->index();
            $table->string('trigger');
            $table->string('trigger_key')->unique();
            $table->foreignId('source_publication_id')->constrained('publications');
            $table->string('scenario');
            $table->json('recipient_ids');
            $table->string('status')->default('pending');
            $table->timestamp('started_at')->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        Schema::table('publications', function (Blueprint $table): void {
            $table->foreignId('agent_id')->nullable()->constrained('agents');
            $table->foreignId('exclusive_run_id')->nullable()->constrained('exclusive_publication_runs');
            $table->timestamp('exclusive_checked_at')->nullable();
            $table->unique(['exclusive_run_id', 'agent_id']);
        });
        DB::table('publications')->update(['agent_id' => DB::raw('(SELECT agent_id FROM offers WHERE offers.id = publications.offer_id)')]);
        Schema::create('offer_image_uploads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('offer_image_id')->constrained('offer_images')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('agents');
            $table->bigInteger('owner_id');
            $table->bigInteger('media_id')->nullable();
            $table->unique(['offer_image_id', 'agent_id']);
        });
    }

    public function down(): void
    {
        Schema::table('publication_tasks', fn (Blueprint $table) => $table->dropColumn('external_started_at'));
        Schema::dropIfExists('offer_image_uploads');
        Schema::table('publications', function (Blueprint $table): void {
            $table->dropUnique(['exclusive_run_id', 'agent_id']);
            $table->dropConstrainedForeignId('exclusive_run_id');
            $table->dropConstrainedForeignId('agent_id');
            $table->dropColumn('exclusive_checked_at');
        });
        Schema::dropIfExists('exclusive_publication_runs');
        Schema::dropIfExists('exclusive_object_locks');
        Schema::table('offers', function (Blueprint $table): void {
            $table->dropIndex(['city', 'agent_id']);
            $table->dropColumn(['exclusive', 'exclusive_transition', 'exclusive_skip_reason']);
        });
    }
};
