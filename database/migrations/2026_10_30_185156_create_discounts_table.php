<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Discounts a school offers: sibling (applied automatically at billing), staff child and
     * custom (given to students by hand). A percentage (basis points: 1250 = 12.5%) or a fixed
     * amount in pesewas, on every fee item or only the listed ones.
     */
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('kind', 20);
            $table->string('type', 10);
            $table->unsignedBigInteger('value');
            // null = every fee item on the bill; otherwise a list of fee item ids.
            $table->json('fee_item_ids')->nullable();
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
        Schema::dropIfExists('discounts');
    }
};
