<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\TeachingAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The teacher of a subject in a stream for a year (decides who enters its scores).
 *
 * @property int $id
 * @property int $academic_year_id
 * @property int $stream_id
 * @property int $subject_id
 * @property int $staff_id
 * @property-read Staff $staff
 * @property-read Subject $subject
 * @property-read Stream $stream
 */
#[Fillable(['academic_year_id', 'stream_id', 'subject_id', 'staff_id'])]
class TeachingAssignment extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<TeachingAssignmentFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<Stream, $this>
     */
    public function stream(): BelongsTo
    {
        return $this->belongsTo(Stream::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('staff')->logOnly(['academic_year_id', 'stream_id', 'subject_id', 'staff_id'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
