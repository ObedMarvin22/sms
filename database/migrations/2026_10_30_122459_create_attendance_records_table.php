<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One student's mark on a register. Linked to the enrolment (SPEC: attendance links to an
     * enrolment); student_id is kept too, for a child's attendance history without joins.
     */
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('attendance_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrolment_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('status', 10);
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'attendance_session_id', 'student_id'], 'attendance_records_unique');
            $table->index(['school_id', 'student_id', 'status'], 'attendance_records_student_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
