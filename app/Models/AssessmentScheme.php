<?php

namespace App\Models;

use App\Enums\AssessmentMode;
use App\Enums\SchoolLevel;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\AssessmentSchemeFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * How one level is assessed in one academic year.
 *
 * @property int $id
 * @property int $academic_year_id
 * @property SchoolLevel $level
 * @property AssessmentMode $mode
 * @property-read AcademicYear $academicYear
 * @property-read Collection<int, AssessmentComponent> $components
 */
class AssessmentScheme extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AssessmentSchemeFactory> */
    use HasFactory;

    use LogsActivity;

    /** @var list<string> */
    protected $fillable = ['academic_year_id', 'level', 'mode'];

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return HasMany<AssessmentComponent, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(AssessmentComponent::class)->orderBy('sequence')->orderBy('id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('grades')->logOnly(['academic_year_id', 'level', 'mode'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => SchoolLevel::class,
            'mode' => AssessmentMode::class,
        ];
    }
}
