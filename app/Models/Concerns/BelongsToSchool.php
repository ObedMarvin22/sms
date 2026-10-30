<?php

namespace App\Models\Concerns;

use App\Exceptions\CrossSchoolWrite;
use App\Exceptions\NoCurrentSchool;
use App\Models\School;
use App\Support\Tenancy\CurrentSchool;
use App\Support\Tenancy\SchoolScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Required on every school-owned model (enforced by tests/Arch/TenancyTest.php).
 *
 * - Queries only see the current school's rows (SchoolScope).
 * - New rows get the current school's id; writing into another school throws.
 * - school_id never changes after creation.
 *
 * @property int $school_id
 * @property-read School $school
 */
trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        static::addGlobalScope(new SchoolScope);

        static::creating(function (Model $model): void {
            $currentSchoolId = app(CurrentSchool::class)->id();
            $schoolId = $model->getAttribute('school_id');

            if ($schoolId === null) {
                // No school anywhere: refuse rather than write an orphan row.
                $model->setAttribute('school_id', $currentSchoolId ?? throw NoCurrentSchool::make());

                return;
            }

            if ($currentSchoolId !== null && (int) $schoolId !== $currentSchoolId) {
                throw CrossSchoolWrite::creating($model, $currentSchoolId);
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('school_id')) {
                throw CrossSchoolWrite::moving($model);
            }
        });
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
