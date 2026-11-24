<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\AssessmentCommentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A teacher's comment on one child in one early-years learning area for a term.
 *
 * @property int $id
 * @property int $term_id
 * @property int $stream_id
 * @property int $subject_id
 * @property int $student_id
 * @property string $comment
 * @property int|null $entered_by
 * @property-read Student $student
 * @property-read Subject $subject
 */
class AssessmentComment extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AssessmentCommentFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * As with marks: first entries are not logged, changes and clearings are. After a submit,
     * SaveComment logs first entries too.
     *
     * @var list<string>
     */
    protected static array $recordEvents = ['updated', 'deleted'];

    /** @var list<string> */
    protected $fillable = ['term_id', 'stream_id', 'subject_id', 'student_id', 'comment', 'entered_by'];

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('grades')
            ->logOnly(['term_id', 'stream_id', 'subject_id', 'student_id', 'comment'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }
}
