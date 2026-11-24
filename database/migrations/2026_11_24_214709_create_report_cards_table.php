<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One student's report card for a term: the overall result (average, aggregate, position),
     * worked out from their term results. Later steps add remarks, the PDF and publishing.
     */
    public function up(): void
    {
        Schema::create('report_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->constrained()->restrictOnDelete();
            $table->foreignId('stream_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('status', 12);
            $table->decimal('average', 5, 2)->nullable();
            $table->unsignedTinyInteger('aggregate')->nullable();
            $table->unsignedSmallInteger('position')->nullable();
            $table->unsignedSmallInteger('class_size')->default(0);
            $table->unsignedTinyInteger('subjects_count')->default(0);
            $table->timestamps();

            $table->unique(['school_id', 'term_id', 'student_id']);
            $table->index(['school_id', 'term_id', 'stream_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_cards');
    }
};
