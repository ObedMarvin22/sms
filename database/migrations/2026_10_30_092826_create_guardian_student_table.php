<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Links a guardian to a child: how they are related, what the parent app may show them,
     * whether they are the primary contact and whether they may collect the child.
     */
    public function up(): void
    {
        Schema::create('guardian_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('guardian_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('relationship', 20);
            $table->string('access_level', 10)->default('full');
            // true or NULL, never false: the unique index then allows one primary contact per child.
            $table->boolean('is_primary')->nullable();
            $table->boolean('can_pick_up')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'guardian_id', 'student_id'], 'guardian_student_unique');
            $table->unique(['school_id', 'student_id', 'is_primary'], 'guardian_student_primary_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guardian_student');
    }
};
