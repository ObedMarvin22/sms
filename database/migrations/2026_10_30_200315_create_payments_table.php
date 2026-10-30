<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Money received for a student, with its receipt number. Never edited or deleted: a mistake
     * is reversed (reversed_* columns plus a reversing ledger entry), and the receipt number is
     * never reused.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('receipt_number', 40);
            $table->unsignedBigInteger('amount');
            $table->string('method', 12);
            // Bank slip number or mobile money transaction id (null for cash).
            $table->string('reference', 60)->nullable();
            $table->date('paid_on');
            // Private disk: schools/{id}/payments/...
            $table->string('proof_path')->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reversal_reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'receipt_number']);
            // The same slip or transaction can be recorded once per method (NULLs, i.e. cash, never clash).
            $table->unique(['school_id', 'method', 'reference'], 'payments_reference_unique');
            $table->index(['school_id', 'student_id'], 'payments_student_index');
            $table->index(['school_id', 'paid_on'], 'payments_paid_on_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
