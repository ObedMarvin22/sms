<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A school's SMS credit, in units (one unit = one SMS segment). Append-only like the fee
     * ledger: top-ups are positive, messages negative, refunds of failed messages positive.
     * Balance = sum of units. Schools see only their own (school_id, school scope).
     */
    public function up(): void
    {
        Schema::create('message_credit_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->string('type', 10);
            $table->integer('units');
            $table->string('note', 150)->nullable();
            $table->foreignId('sms_message_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');

            $table->index(['school_id', 'created_at'], 'message_credit_ledger_school_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_credit_ledger');
    }
};
