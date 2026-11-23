<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A piece of marked work (a test, an exercise, the exam) for one class, subject and term, under
     * one part of the level's scheme. Tied to the class and subject, not to the teaching assignment,
     * so it survives a change of teacher.
     */
    public function up(): void
    {
        Schema::create('assessment_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->constrained()->restrictOnDelete();
            $table->foreignId('stream_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('assessment_component_id')->constrained()->restrictOnDelete();
            $table->string('name', 80);
            $table->date('given_on');
            $table->unsignedSmallInteger('max_mark');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'term_id', 'stream_id', 'subject_id', 'assessment_component_id', 'name'], 'assessment_tasks_unique_name');
            $table->index(['school_id', 'term_id', 'stream_id', 'subject_id'], 'assessment_tasks_sheet');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_tasks');
    }
};
