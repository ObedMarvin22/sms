<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One press of "Bill students" for a term: queued in chunks, finished when every student in
     * it has a bill or was skipped (skipped: student id => name and reason).
     */
    public function up(): void
    {
        Schema::create('billing_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 10)->default('queued');
            $table->unsignedInteger('total_students');
            $table->json('skipped')->nullable();
            $table->unsignedBigInteger('total_net')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'term_id', 'status'], 'billing_runs_term_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_runs');
    }
};
