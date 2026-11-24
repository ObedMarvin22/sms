<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Database\Factories\AssessmentTaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One piece of marked work, e.g. "Class test 1, out of 20", for a class, subject and term.
 *
 * @property int $id
 * @property int $term_id
 * @property int $stream_id
 * @property int $subject_id
 * @property int $assessment_component_id
 * @property string $name
 * @property CarbonImmutable $given_on
 * @property int $max_mark
 * @property int|null $created_by
 * @property-read Term $term
 * @property-read Stream $stream
 * @property-read Subject $subject
 * @property-read AssessmentComponent $component
 */
class AssessmentTask extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AssessmentTaskFactory> */
    use HasFactory;

    use LogsActivity;

    /** @var list<string> */
    protected $fillable = ['term_id', 'stream_id', 'subject_id', 'assessment_component_id', 'name', 'given_on', 'max_mark', 'created_by'];

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<Stream, $this>
     */
    public function stream(): BelongsTo
    {
        return $this->belongsTo(Stream::class);
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<AssessmentComponent, $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(AssessmentComponent::class, 'assessment_component_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('grades')
            ->logOnly(['term_id', 'stream_id', 'subject_id', 'assessment_component_id', 'name', 'given_on', 'max_mark'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'given_on' => 'immutable_date',
            'max_mark' => 'integer',
        ];
    }
}
