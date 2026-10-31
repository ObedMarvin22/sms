<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Orange Tree's invoices to schools (platform tables, read only by the console). One live
     * invoice per school per term: `live` is true, or NULL once voided, so the unique index lets a
     * voided term be invoiced again.
     */
    public function up(): void
    {
        Schema::create('saas_invoice_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('saas_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            // Copied when issued, so a later price change never alters an invoice.
            $table->string('plan_name', 60);
            $table->unsignedInteger('rate_per_student');
            $table->date('census_on');
            $table->unsignedInteger('student_count');
            $table->unsignedBigInteger('amount');
            $table->timestamp('issued_at');
            $table->date('due_on');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete(); // null: the census command
            $table->boolean('live')->nullable()->default(true);
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'term_id', 'live'], 'saas_invoices_one_live_per_term');
            $table->index(['due_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saas_invoices');
        Schema::dropIfExists('saas_invoice_sequences');
    }
};
