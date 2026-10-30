<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One spreadsheet row of an import, already checked by the dry run. A row is imported once:
     * the job only takes rows that are still pending, so a retried job cannot admit twice.
     */
    public function up(): void
    {
        Schema::create('student_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('data');
            $table->string('status', 10)->default('pending');
            $table->string('error', 500)->nullable();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'student_import_id', 'row_number'], 'student_import_rows_unique');
            $table->index(['school_id', 'student_import_id', 'status'], 'student_import_rows_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_import_rows');
    }
};
