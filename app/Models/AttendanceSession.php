<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Database\Factories\AttendanceSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A stream's register for one day.
 *
 * @property int $id
 * @property int $stream_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property CarbonImmutable $date
 * @property int|null $taken_by
 * @property CarbonImmutable|null $taken_at
 * @property-read Stream $stream
 * @property-read User|null $takenBy
 */
#[Fillable(['stream_id', 'academic_year_id', 'term_id', 'date'])]
class AttendanceSession extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AttendanceSessionFactory> */
    use HasFactory;

    /**
     * @return HasMany<AttendanceRecord, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * @return BelongsTo<Stream, $this>
     */
    public function stream(): BelongsTo
    {
        return $this->belongsTo(Stream::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function takenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'taken_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'taken_at' => 'immutable_datetime',
        ];
    }
}
