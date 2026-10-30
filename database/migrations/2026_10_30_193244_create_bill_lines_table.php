<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One line of a bill: a fee item (positive) or a discount / scholarship (negative). The
     * description is copied at billing so the bill reads the same if names change later.
     */
    public function up(): void
    {
        Schema::create('bill_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->string('kind', 12);
            $table->foreignId('fee_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('discount_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('student_discount_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 150);
            $table->bigInteger('amount');
            $table->timestamps();

            $table->unique(['school_id', 'bill_id', 'sequence'], 'bill_lines_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bill_lines');
    }
};
