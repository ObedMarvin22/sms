<?php

namespace App\Models;

use App\Enums\SheetStatus;
use App\Models\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Database\Factories\AssessmentSheetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The submit/unlock state of one class's mark sheet for a subject and term.
 *
 * @property int $id
 * @property int $term_id
 * @property int $stream_id
 * @property int $subject_id
 * @property SheetStatus $status
 * @property int|null $submitted_by
 * @property CarbonImmutable|null $submitted_at
 * @property int|null $unlocked_by
 * @property CarbonImmutable|null $unlocked_at
 * @property string|null $unlock_reason
 * @property-read Term $term
 * @property-read Stream $stream
 * @property-read Subject $subject
 * @property-read User|null $submitter
 * @property-read User|null $unlocker
 */
class AssessmentSheet extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AssessmentSheetFactory> */
    use HasFactory;

    use LogsActivity;

    /** @var list<string> */
    protected $fillable = ['term_id', 'stream_id', 'subject_id', 'status', 'submitted_by', 'submitted_at', 'unlocked_by', 'unlocked_at', 'unlock_reason'];

    public function isLocked(): bool
    {
        return $this->status === SheetStatus::Submitted;
    }

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
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function unlocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unlocked_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('grades')
            ->logOnly(['term_id', 'stream_id', 'subject_id', 'status', 'submitted_by', 'unlocked_by', 'unlock_reason'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SheetStatus::class,
            'submitted_at' => 'immutable_datetime',
            'unlocked_at' => 'immutable_datetime',
        ];
    }
}
