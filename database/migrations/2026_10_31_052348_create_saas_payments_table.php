<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Money a school paid Orange Tree against an invoice, recorded by hand in the console.
     * Never changed or deleted (the model refuses).
     */
    public function up(): void
    {
        Schema::create('saas_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saas_invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->date('paid_on');
            $table->string('method', 20);
            $table->string('reference', 100)->nullable();
            $table->string('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saas_payments');
    }
};
