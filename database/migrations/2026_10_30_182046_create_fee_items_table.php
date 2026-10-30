<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The things a school charges for (tuition, PTA dues, feeding, ...). Amounts live in fee
     * structures, per term, class and residence. Items are archived (is_active), never deleted
     * once used, so old bills keep their names.
     */
    public function up(): void
    {
        Schema::create('fee_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('category', 20);
            // Optional items (transport, feeding, ...) are billed only to students who opted in.
            $table->boolean('is_optional')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_items');
    }
};
