<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->string('original_url', 500);
            $table->unsignedBigInteger('media_id')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->dateTime('created_at')->useCurrent();

            $table->index(['offer_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_images');
    }
};