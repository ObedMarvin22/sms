<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Where a report card's generated PDF is stored (private disk) and when it was made.
     */
    public function up(): void
    {
        Schema::table('report_cards', function (Blueprint $table) {
            $table->string('pdf_path')->nullable()->after('head_remark');
            $table->timestamp('generated_at')->nullable()->after('pdf_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('report_cards', function (Blueprint $table) {
            $table->dropColumn(['pdf_path', 'generated_at']);
        });
    }
};
