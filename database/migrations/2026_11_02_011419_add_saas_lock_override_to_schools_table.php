<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Orange Tree may keep a school with an overdue invoice open until a date (e.g. after agreeing
     * a payment plan) instead of letting it become read-only (Pilot preparation step 4).
     */
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->date('saas_lock_override_until')->nullable()->after('plan_id');
            $table->string('saas_lock_override_reason')->nullable()->after('saas_lock_override_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['saas_lock_override_until', 'saas_lock_override_reason']);
        });
    }
};
