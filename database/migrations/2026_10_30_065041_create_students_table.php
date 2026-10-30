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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->string('admission_number', 40);
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('other_names', 100)->nullable();
            $table->string('gender', 10)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->date('admitted_on');
            $table->string('status', 15)->default('active');
            $table->timestamps();
            $table->softDeletes();

            // Second safety net behind the locked generator.
            $table->unique(['school_id', 'admission_number']);
            $table->index(['school_id', 'status', 'last_name', 'first_name'], 'students_school_status_name_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
