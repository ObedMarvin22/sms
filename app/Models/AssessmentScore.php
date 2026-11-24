<?php

namespace App\Models;

use App\Enums\ScoreStatus;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\AssessmentScoreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One student's result on one task.
 *
 * @property int $id
 * @property int $assessment_task_id
 * @property int $student_id
 * @property ScoreStatus $status
 * @property string|null $mark decimal with one place, e.g. "12.5"; null unless marked
 * @property int|null $entered_by
 * @property-read AssessmentTask $task
 * @property-read Student $student
 */
class AssessmentScore extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AssessmentScoreFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * First entries are not logged (a school writes tens of thousands a term); every change to
     * a mark already given, and every clearing of one, is.
     *
     * @var list<string>
     */
    protected static array $recordEvents = ['updated', 'deleted'];

    /** @var list<string> */
    protected $fillable = ['assessment_task_id', 'student_id', 'status', 'mark', 'entered_by'];

    /**
     * @return BelongsTo<AssessmentTask, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(AssessmentTask::class, 'assessment_task_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * What the mark sheet shows in the cell: "12.5", "12", "A" or "X".
     */
    public function display(): string
    {
        return $this->status->key() ?? rtrim(rtrim((string) $this->mark, '0'), '.');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('grades')
            ->logOnly(['assessment_task_id', 'student_id', 'status', 'mark'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ScoreStatus::class,
            'mark' => 'decimal:1',
        ];
    }
}
