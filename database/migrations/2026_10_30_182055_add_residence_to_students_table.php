<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Day student or boarder, now. Each bill records the value it was billed at, so a change
     * between terms never rewrites history.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('residence', 10)->default('day')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('residence');
        });
    }
};
