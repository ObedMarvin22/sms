<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One band of a grade scale: totals from min_score up (to the next band) get this grade.
     * points feed aggregates (BECE/WASSCE style: 1 is best).
     */
    public function up(): void
    {
        Schema::create('grade_bands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('grade_scale_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('min_score');
            $table->string('grade', 4);
            $table->string('label', 40);
            $table->unsignedTinyInteger('points')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'grade_scale_id', 'min_score']);
            $table->unique(['school_id', 'grade_scale_id', 'grade']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_bands');
    }
};
