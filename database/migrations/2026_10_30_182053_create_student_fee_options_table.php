<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Optional fee items a student takes (transport, feeding, ...). Billed every term, at the
     * amount in the student's fee structure, until removed.
     */
    public function up(): void
    {
        Schema::create('student_fee_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_item_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'student_id', 'fee_item_id'], 'student_fee_options_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_fee_options');
    }
};
