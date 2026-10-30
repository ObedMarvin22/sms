<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Users belong to one school; null school_id = Orange Tree platform user.
     * Email is unique per school, not globally.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('school_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->restrictOnDelete();

            $table->dropUnique(['email']);
            $table->unique(['school_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The foreign key uses the (school_id, email) index, so drop the key first.
            $table->dropForeign(['school_id']);
            $table->dropUnique(['school_id', 'email']);
            $table->dropColumn('school_id');
            $table->unique('email');
        });
    }
};
