<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\TermResultFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's result in one subject for a term (a snapshot written on submit).
 *
 * @property int $id
 * @property int $term_id
 * @property int $stream_id
 * @property int $subject_id
 * @property int $student_id
 * @property string|null $total e.g. "64.30"
 * @property string|null $grade
 * @property string|null $grade_label
 * @property int|null $points
 * @property int|null $subject_position
 * @property list<array{name: string, kind?: string, weight: int, score: float|null}> $parts
 * @property-read Subject $subject
 * @property-read Student $student
 */
class TermResult extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<TermResultFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['term_id', 'stream_id', 'subject_id', 'student_id', 'total', 'grade', 'grade_label', 'points', 'subject_position', 'parts'];

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'points' => 'integer',
            'subject_position' => 'integer',
            'parts' => 'array',
        ];
    }
}
