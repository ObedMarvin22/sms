<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The instalment plan a student follows in a term. No row means the term's default plan (or,
     * with none, the whole bill due when the term starts).
     */
    public function up(): void
    {
        Schema::create('student_instalment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->constrained()->restrictOnDelete();
            $table->foreignId('instalment_plan_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'student_id', 'term_id'], 'student_instalment_plans_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_instalment_plans');
    }
};
