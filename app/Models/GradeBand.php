<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\GradeBandFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One band of a grade scale, e.g. 80 and above: "A1", Excellent, 1 point.
 *
 * @property int $id
 * @property int $grade_scale_id
 * @property int $min_score whole percent
 * @property string $grade
 * @property string $label
 * @property int|null $points for aggregates; 1 is best
 * @property-read GradeScale $scale
 */
class GradeBand extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<GradeBandFactory> */
    use HasFactory;

    use LogsActivity;

    /** @var list<string> */
    protected $fillable = ['grade_scale_id', 'min_score', 'grade', 'label', 'points'];

    /**
     * @return BelongsTo<GradeScale, $this>
     */
    public function scale(): BelongsTo
    {
        return $this->belongsTo(GradeScale::class, 'grade_scale_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('grades')->logOnly(['grade_scale_id', 'min_score', 'grade', 'label', 'points'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['min_score' => 'integer', 'points' => 'integer'];
    }
}
