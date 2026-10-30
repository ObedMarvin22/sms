<?php

namespace App\Models;

use App\Enums\EnrolmentStatus;
use App\Models\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Database\Factories\EnrolmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $student_id
 * @property int $academic_year_id
 * @property int $stream_id
 * @property int|null $programme_id
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property EnrolmentStatus $status
 * @property string|null $reason
 * @property bool $is_current
 * @property-read Student $student
 * @property-read AcademicYear $academicYear
 * @property-read Stream $stream
 * @property-read Programme|null $programme
 */
#[Fillable(['student_id', 'academic_year_id', 'stream_id', 'programme_id', 'starts_on', 'ends_on', 'reason'])]
class Enrolment extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<EnrolmentFactory> */
    use HasFactory;

    use LogsActivity;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'active'];

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<Stream, $this>
     */
    public function stream(): BelongsTo
    {
        return $this->belongsTo(Stream::class);
    }

    /**
     * @return BelongsTo<Programme, $this>
     */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    /**
     * @param  Builder<Enrolment>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('is_current', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('students')
            ->logOnly(['student_id', 'academic_year_id', 'stream_id', 'programme_id', 'starts_on', 'ends_on', 'status', 'reason', 'is_current'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * Stored as true or NULL (never false): one current enrolment per student (unique index).
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
            'status' => EnrolmentStatus::class,
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
        ];
    }
}
