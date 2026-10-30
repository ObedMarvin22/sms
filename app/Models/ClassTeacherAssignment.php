<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\ClassTeacherAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The class teacher of a stream for a year (takes its attendance).
 *
 * @property int $id
 * @property int $academic_year_id
 * @property int $stream_id
 * @property int $staff_id
 * @property-read Staff $staff
 */
#[Fillable(['academic_year_id', 'stream_id', 'staff_id'])]
class ClassTeacherAssignment extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ClassTeacherAssignmentFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('staff')->logOnly(['academic_year_id', 'stream_id', 'staff_id'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
