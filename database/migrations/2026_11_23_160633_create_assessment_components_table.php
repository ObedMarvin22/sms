<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The weighted parts of a scheme (class work, tests, project, exam). Weights are whole
     * percentages and add up to 100 per scheme (checked by SaveAssessmentScheme).
     */
    public function up(): void
    {
        Schema::create('assessment_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('assessment_scheme_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('kind', 20);
            $table->unsignedTinyInteger('weight');
            $table->unsignedTinyInteger('sequence')->default(0);
            $table->timestamps();

            $table->unique(['school_id', 'assessment_scheme_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_components');
    }
};
