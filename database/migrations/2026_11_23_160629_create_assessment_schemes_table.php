<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * How each level is assessed in an academic year (SPEC section 7): weighted parts that add up
     * to 100, or descriptive (comments only, for Creche, Nursery and KG).
     */
    public function up(): void
    {
        Schema::create('assessment_schemes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->string('level', 20);
            $table->string('mode', 20);
            $table->timestamps();

            $table->unique(['school_id', 'academic_year_id', 'level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_schemes');
    }
};
