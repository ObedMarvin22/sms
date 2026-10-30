<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Where the school's Paystack money settles, for display only ("GCB Bank · ****1234 ·
     * Greenhill Academy Ltd"). The subaccount code already exists on schools.
     */
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('paystack_settlement', 150)->nullable()->after('paystack_subaccount_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('paystack_settlement');
        });
    }
};
