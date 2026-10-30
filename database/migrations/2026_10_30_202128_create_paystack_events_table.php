<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Webhook events Paystack sent us (platform table: Paystack does not know our schools). The
     * unique key ("charge.success:123456") makes a repeated delivery a no-op.
     */
    public function up(): void
    {
        Schema::create('paystack_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 120)->unique();
            $table->string('event', 60);
            $table->string('reference', 60)->nullable();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->string('error', 255)->nullable();
            $table->timestamps();

            $table->index('reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paystack_events');
    }
};
