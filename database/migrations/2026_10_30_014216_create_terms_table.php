<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Terms or semesters (per school, see AcademicPeriodType).
     */
    public function up(): void
    {
        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->string('name', 50);
            $table->date('starts_on');
            $table->date('ends_on');
            // Active students on this date are billed for the term (SaaS census).
            $table->date('census_on');
            // true or NULL, never false: the unique index then allows one current term per school.
            $table->boolean('is_current')->nullable();
            $table->timestamps();

            $table->unique(['academic_year_id', 'sequence']);
            $table->unique(['school_id', 'is_current']);
            $table->index(['school_id', 'starts_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terms');
    }
};
