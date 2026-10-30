<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Append-only: rows are only ever inserted (no updated_at; LedgerEntry refuses updates and
     * deletes). A student's balance is the sum of amounts: positive increases what they owe
     * (bills), negative reduces it (payments, credits). Corrections are reversing entries.
     */
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('type', 12);
            $table->bigInteger('amount');
            $table->foreignId('bill_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description', 150);
            $table->date('entry_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');

            $table->index(['school_id', 'student_id'], 'ledger_entries_student_index');
            $table->index(['school_id', 'entry_date'], 'ledger_entries_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
