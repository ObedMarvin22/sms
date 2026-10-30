<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Which subjects a programme has, as core or elective. Carries school_id like every
     * school-owned table, so it is covered by BelongsToSchool and the isolation tests.
     */
    public function up(): void
    {
        Schema::create('programme_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('programme_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);
            $table->timestamps();

            $table->unique(['programme_id', 'subject_id']);
            $table->index('school_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('programme_subject');
    }
};
