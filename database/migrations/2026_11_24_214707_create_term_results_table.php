<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A student's result in one subject for a term: written when the mark sheet is submitted (a
     * snapshot, so later changes to a grade scale never rewrite it) and removed when a head
     * unlocks the sheet. parts holds each part's name, weight and score for the report card.
     */
    public function up(): void
    {
        Schema::create('term_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->constrained()->restrictOnDelete();
            $table->foreignId('stream_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->decimal('total', 5, 2)->nullable();
            $table->string('grade', 4)->nullable();
            $table->string('grade_label', 40)->nullable();
            $table->unsignedTinyInteger('points')->nullable();
            $table->unsignedSmallInteger('subject_position')->nullable();
            $table->json('parts');
            $table->timestamps();

            $table->unique(['school_id', 'term_id', 'stream_id', 'subject_id', 'student_id'], 'term_results_unique');
            $table->index(['school_id', 'term_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('term_results');
    }
};
