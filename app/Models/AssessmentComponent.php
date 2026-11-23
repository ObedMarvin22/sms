<?php

namespace App\Models;

use App\Enums\ComponentKind;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\AssessmentComponentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One weighted part of a scheme, e.g. "Tests" 10%. Tasks (Sprint 5 step 2) sit under it.
 *
 * @property int $id
 * @property int $assessment_scheme_id
 * @property string $name
 * @property ComponentKind $kind
 * @property int $weight whole percent
 * @property int $sequence
 * @property-read AssessmentScheme $scheme
 */
class AssessmentComponent extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AssessmentComponentFactory> */
    use HasFactory;

    use LogsActivity;

    /** @var list<string> */
    protected $fillable = ['assessment_scheme_id', 'name', 'kind', 'weight', 'sequence'];

    /**
     * @return BelongsTo<AssessmentScheme, $this>
     */
    public function scheme(): BelongsTo
    {
        return $this->belongsTo(AssessmentScheme::class, 'assessment_scheme_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('grades')->logOnly(['assessment_scheme_id', 'name', 'kind', 'weight'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ComponentKind::class,
            'weight' => 'integer',
            'sequence' => 'integer',
        ];
    }
}
