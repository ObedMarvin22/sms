<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A student's place in a stream for a stretch of a year. Changes end one enrolment and start
     * another, so the history is never overwritten. Foreign keys restrict deletes: a class,
     * stream or year in use cannot disappear from under a student's history.
     */
    public function up(): void
    {
        Schema::create('enrolments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('stream_id')->constrained()->restrictOnDelete();
            $table->foreignId('programme_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('reason', 255)->nullable();
            // true or NULL, never false: the unique index then allows one current enrolment per student.
            $table->boolean('is_current')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'is_current']);
            $table->index(['school_id', 'academic_year_id', 'stream_id', 'is_current'], 'enrolments_school_year_stream_current_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrolments');
    }
};
