<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * How a term's fees may be paid in parts, e.g. "50% by 18 Sep, the rest by 30 Oct". Steps are
     * in instalment_plan_steps. One plan per term may be the default for students given none.
     */
    public function up(): void
    {
        Schema::create('instalment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            // true or NULL, never false: the unique index then allows one default per term.
            $table->boolean('is_default')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'term_id', 'name'], 'instalment_plans_name_unique');
            $table->unique(['school_id', 'term_id', 'is_default'], 'instalment_plans_default_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instalment_plans');
    }
};
