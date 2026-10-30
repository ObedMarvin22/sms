<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * When a bill's parts are due (from the student's instalment plan). They add up to the
     * bill's net amount exactly; the last one absorbs any pesewa from rounding.
     */
    public function up(): void
    {
        Schema::create('bill_instalments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->date('due_on');
            $table->unsignedBigInteger('amount');
            $table->timestamps();

            $table->unique(['school_id', 'bill_id', 'sequence'], 'bill_instalments_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bill_instalments');
    }
};
