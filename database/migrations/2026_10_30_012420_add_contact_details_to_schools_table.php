<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Contact details shown on report cards and receipts. Phone is stored as +233XXXXXXXXX.
     */
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('phone', 13)->nullable()->after('secondary_colour');
            $table->string('email')->nullable()->after('phone');
            $table->string('address', 500)->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['phone', 'email', 'address']);
        });
    }
};
