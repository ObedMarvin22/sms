<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One line of a fee structure: a fee item and its amount in pesewas (Money cast).
     */
    public function up(): void
    {
        Schema::create('fee_structure_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_item_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->timestamps();

            $table->unique(['school_id', 'fee_structure_id', 'fee_item_id'], 'fee_structure_items_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_structure_items');
    }
};
