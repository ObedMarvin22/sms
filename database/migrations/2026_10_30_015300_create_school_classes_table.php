<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Classes such as "JHS 2". Level is the SchoolLevel enum value (no levels table).
     */
    public function up(): void
    {
        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->string('level', 20);
            $table->string('name', 50);
            // Order within the school, youngest first.
            $table->unsignedSmallInteger('sequence');
            $table->timestamps();

            $table->unique(['school_id', 'name']);
            $table->index(['school_id', 'level', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_classes');
    }
};
