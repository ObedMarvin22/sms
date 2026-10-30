<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Reductions given to one student. Either a school discount (discount_id set; its terms come
     * from the discount) or a scholarship (discount_id null; its own name, sponsor, type, value
     * and items, optionally ending after a term).
     */
    public function up(): void
    {
        Schema::create('student_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('discount_id')->nullable()->constrained()->restrictOnDelete();
            // Scholarship only:
            $table->string('name', 100)->nullable();
            $table->string('sponsor', 100)->nullable();
            $table->string('type', 10)->nullable();
            $table->unsignedBigInteger('value')->nullable();
            $table->json('fee_item_ids')->nullable();
            $table->foreignId('ends_term_id')->nullable()->constrained('terms')->restrictOnDelete();
            $table->timestamps();

            // A school discount once per student (NULL discount_ids, i.e. scholarships, never clash).
            $table->unique(['school_id', 'student_id', 'discount_id'], 'student_discounts_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_discounts');
    }
};
