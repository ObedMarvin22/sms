<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Database\Factories\TermFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A term or semester (see AcademicPeriodType).
 *
 * @property int $id
 * @property int $academic_year_id
 * @property int $sequence 1-based within the year
 * @property string $name
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property CarbonImmutable $census_on Active students on this date are billed (SaaS census)
 * @property bool $is_current
 * @property-read AcademicYear $academicYear
 */
#[Fillable(['academic_year_id', 'sequence', 'name', 'starts_on', 'ends_on', 'census_on'])]
class Term extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<TermFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @param  Builder<Term>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('is_current', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('academic')->logOnly(['name', 'starts_on', 'ends_on', 'census_on', 'is_current'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * Stored as true or NULL (never false): one current term per school (unique index).
     *
     * @return Attribute<bool, bool|null>
     */
    protected function isCurrent(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): bool => (bool) $value,
            set: fn (mixed $value): ?bool => $value ? true : null,
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'census_on' => 'immutable_date',
        ];
    }
}
