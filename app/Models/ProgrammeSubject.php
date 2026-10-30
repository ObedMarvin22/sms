<?php

namespace App\Models;

use App\Enums\SubjectType;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\ProgrammeSubjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A subject in a programme, as core or elective. A pivot model so it gets school_id
 * (BelongsToSchool) like every other school-owned row.
 *
 * @property int $id
 * @property int $programme_id
 * @property int $subject_id
 * @property SubjectType $type
 */
class ProgrammeSubject extends Pivot
{
    use BelongsToSchool;

    /** @use HasFactory<ProgrammeSubjectFactory> */
    use HasFactory;

    public $incrementing = true;

    protected $table = 'programme_subject';

    /** @var list<string> */
    protected $fillable = ['programme_id', 'subject_id', 'type'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['type' => SubjectType::class];
    }
}
