<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One instalment: the share of the bill due by a date, in basis points (5000 = 50%). A plan's
     * steps add up to 10000.
     */
    public function up(): void
    {
        Schema::create('instalment_plan_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('instalment_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->date('due_on');
            $table->unsignedSmallInteger('percent');
            $table->timestamps();

            $table->unique(['school_id', 'instalment_plan_id', 'sequence'], 'instalment_plan_steps_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instalment_plan_steps');
    }
};
