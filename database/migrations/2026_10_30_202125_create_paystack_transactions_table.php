<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One online payment attempt. The reference is unique across all schools: the webhook finds
     * the school from it. amount goes to the student's account; charge is Paystack's fee when the
     * school passes it on (total = amount + charge is what the parent pays).
     */
    public function up(): void
    {
        Schema::create('paystack_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('reference', 60)->unique();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('charge')->default(0);
            $table->unsignedBigInteger('total');
            $table->string('email', 150);
            $table->string('status', 10)->default('pending');
            // Why a bursar needs to look (wrong amount, failed after recording, ...).
            $table->string('attention', 255)->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('gateway_response')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status'], 'paystack_transactions_status_index');
            $table->index(['school_id', 'student_id'], 'paystack_transactions_student_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paystack_transactions');
    }
};
