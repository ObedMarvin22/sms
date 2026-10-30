<?php

use App\Enums\SchoolStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Platform table: no school_id. Each row is one tenant.
     */
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // 63 characters is the DNS limit for one subdomain label.
            $table->string('subdomain', 63)->unique();
            // A string, not a MySQL ENUM, so new statuses need no ALTER TABLE.
            $table->string('status', 20)->default(SchoolStatus::Trial->value)->index();
            $table->string('logo_path')->nullable();
            $table->string('primary_colour', 7)->nullable();
            $table->string('secondary_colour', 7)->nullable();
            $table->json('levels_offered');
            // Becomes a link to the plans table in Sprint 4 (SaaS billing).
            $table->string('plan')->nullable();
            $table->string('paystack_subaccount_code')->nullable()->unique();
            // SMS alphanumeric sender IDs are at most 11 characters.
            $table->string('sms_sender_id', 11)->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
