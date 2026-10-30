<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A student's bill for a term: this term's charges only, in pesewas. arrears is the unpaid
     * balance just before the bill (from the ledger), shown for information; it is never charged
     * again. Bills are never edited: corrections are ledger adjustments (step 3).
     */
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrolment_id')->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->string('residence', 10);
            $table->foreignId('billing_run_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('gross');
            $table->unsignedBigInteger('reductions');
            $table->unsignedBigInteger('net');
            // Signed: a student in credit has negative arrears.
            $table->bigInteger('arrears');
            $table->timestamp('issued_at');
            $table->timestamps();

            $table->unique(['school_id', 'student_id', 'term_id'], 'bills_student_term_unique');
            $table->index(['school_id', 'term_id', 'school_class_id'], 'bills_term_class_index');
            $table->index(['school_id', 'billing_run_id'], 'bills_run_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
