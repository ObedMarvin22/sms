<?php

namespace App\Models;

use App\Enums\ReportCardStatus;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\ReportCardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A student's report card for a term.
 *
 * @property int $id
 * @property int $term_id
 * @property int $stream_id
 * @property int $student_id
 * @property ReportCardStatus $status
 * @property string|null $average e.g. "68.25"
 * @property int|null $aggregate JHS/SHS: lower is better
 * @property int|null $position
 * @property int $class_size
 * @property int $subjects_count
 * @property string|null $teacher_remark
 * @property string|null $conduct a Conduct value
 * @property string|null $interest
 * @property string|null $head_remark
 * @property-read Student $student
 * @property-read Stream $stream
 * @property-read Term $term
 */
class ReportCard extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ReportCardFactory> */
    use HasFactory;

    use LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'term_id', 'stream_id', 'student_id', 'status', 'average', 'aggregate', 'position', 'class_size', 'subjects_count',
        'teacher_remark', 'conduct', 'interest', 'head_remark',
    ];

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Stream, $this>
     */
    public function stream(): BelongsTo
    {
        return $this->belongsTo(Stream::class);
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('grades')->logOnly(['status', 'teacher_remark', 'conduct', 'interest', 'head_remark'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReportCardStatus::class,
            'average' => 'decimal:2',
            'aggregate' => 'integer',
            'position' => 'integer',
            'class_size' => 'integer',
            'subjects_count' => 'integer',
        ];
    }
}
