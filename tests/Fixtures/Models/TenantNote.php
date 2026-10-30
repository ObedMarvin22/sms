<?php

namespace Tests\Fixtures\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test-only school-owned model for exercising BelongsToSchool before real ones exist.
 *
 * @property int $id
 * @property int $school_id
 * @property string $body
 */
#[Fillable(['school_id', 'body'])]
class TenantNote extends Model
{
    use BelongsToSchool;

    /**
     * A MySQL TEMPORARY table: unlike CREATE TABLE it does not commit the
     * RefreshDatabase transaction, and it disappears with the connection.
     *
     * No secondary indexes: Laravel adds them with a separate ALTER TABLE, which
     * does commit, and RefreshDatabase would then re-migrate for every test.
     */
    public static function createTable(): void
    {
        Schema::create('tenant_notes', function (Blueprint $table) {
            $table->temporary();
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->string('body');
            $table->timestamps();
        });
    }
}
