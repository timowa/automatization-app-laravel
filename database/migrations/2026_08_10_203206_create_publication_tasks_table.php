<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('publication_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publication_id')->constrained();
            $table->string('type');
            $table->string('status');
            $table->string('error')->nullable();
            $table->string('external_id')->nullable();
            $table->foreignId('dependent_task_id')->nullable()->constrained('publication_tasks')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publication_tasks');
    }
};
