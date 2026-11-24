<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The words on a report card: the class teacher's remark, conduct and interest, and the
     * head's remark. New, empty columns on a table with no data yet in production.
     */
    public function up(): void
    {
        Schema::table('report_cards', function (Blueprint $table) {
            $table->string('teacher_remark', 400)->nullable()->after('subjects_count');
            $table->string('conduct', 30)->nullable()->after('teacher_remark');
            $table->string('interest', 100)->nullable()->after('conduct');
            $table->string('head_remark', 400)->nullable()->after('interest');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('report_cards', function (Blueprint $table) {
            $table->dropColumn(['teacher_remark', 'conduct', 'interest', 'head_remark']);
        });
    }
};
