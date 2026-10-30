<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Every SMS a school sent or tried to send: queued (units taken), sent, failed (units given
     * back) or not_sent (no credit, nothing taken).
     */
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->string('to', 13);
            $table->text('message');
            $table->unsignedSmallInteger('segments');
            $table->string('sender', 11);
            // Which notice it was (absence, payment, ...).
            $table->string('event', 30);
            $table->string('status', 10)->default('queued');
            $table->string('provider_id', 100)->nullable();
            $table->string('error', 255)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status'], 'sms_messages_status_index');
            $table->index(['school_id', 'created_at'], 'sms_messages_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};
