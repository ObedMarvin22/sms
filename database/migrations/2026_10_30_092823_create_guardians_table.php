<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A parent or guardian, owned by one school (DECISIONS.md: parent accounts are per school).
     * The phone (+233XXXXXXXXX) identifies them: one guardian per phone per school, so siblings
     * share a record. user_id is filled when they activate the parent app (Sprint 4).
     */
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->string('phone', 13);
            $table->string('alt_phone', 13)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('occupation', 100)->nullable();
            $table->string('address', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'phone']);
            $table->index(['school_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guardians');
    }
};
