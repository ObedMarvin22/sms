<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The streams an announcement went to (only for audience "streams").
     */
    public function up(): void
    {
        Schema::create('announcement_streams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stream_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'announcement_id', 'stream_id'], 'announcement_streams_unique');
            $table->index(['school_id', 'stream_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcement_streams');
    }
};
