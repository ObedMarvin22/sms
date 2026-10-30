<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->string('name', 20);
            $table->date('starts_on');
            $table->date('ends_on');
            // true or NULL, never false: the unique index then allows one current year per school.
            $table->boolean('is_current')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'name']);
            $table->unique(['school_id', 'is_current']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};
