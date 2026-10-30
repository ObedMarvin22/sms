<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\StudentInstalmentPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The instalment plan a student follows in a term (no row: the term's default).
 *
 * @property int $id
 * @property int $student_id
 * @property int $term_id
 * @property int $instalment_plan_id
 * @property-read InstalmentPlan $plan
 */
#[Fillable(['student_id', 'term_id', 'instalment_plan_id'])]
class StudentInstalmentPlan extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StudentInstalmentPlanFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * @return BelongsTo<InstalmentPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(InstalmentPlan::class, 'instalment_plan_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('fees')
            ->logOnly(['student_id', 'term_id', 'instalment_plan_id'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }
}
