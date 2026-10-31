<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Orange Tree's price list (platform table): a rate per active student per term, in pesewas.
     * Each school is on one plan. The old free-text schools.plan column stays, unused, until a
     * later clean-up (dropping it needs Obed's OK).
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->unsignedInteger('rate_per_student');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('plan')->constrained()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
        });
        Schema::dropIfExists('plans');
    }
};
