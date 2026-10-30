<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Database\Factories\AcademicYearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $name e.g. "2026/2027"
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property bool $is_current
 * @property-read Collection<int, Term> $terms
 */
#[Fillable(['name', 'starts_on', 'ends_on'])]
class AcademicYear extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AcademicYearFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * @return HasMany<Term, $this>
     */
    public function terms(): HasMany
    {
        return $this->hasMany(Term::class)->orderBy('sequence');
    }

    /**
     * @param  Builder<AcademicYear>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('is_current', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('academic')->logOnly(['name', 'starts_on', 'ends_on', 'is_current'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * Stored as true or NULL (never false) so the unique (school_id, is_current) index
     * allows only one current year per school.
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
        ];
    }
}
