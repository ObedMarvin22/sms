<?php

namespace App\Models;

use App\Enums\SchoolLevel;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\GradeScaleFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * How one level's term totals turn into grades.
 *
 * @property int $id
 * @property SchoolLevel $level
 * @property string $name
 * @property-read Collection<int, GradeBand> $bands highest first
 */
class GradeScale extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<GradeScaleFactory> */
    use HasFactory;

    use LogsActivity;

    /** @var list<string> */
    protected $fillable = ['level', 'name'];

    /**
     * @return HasMany<GradeBand, $this>
     */
    public function bands(): HasMany
    {
        return $this->hasMany(GradeBand::class)->orderByDesc('min_score');
    }

    /**
     * The band for a term total. The total is rounded to a whole number first (half up), so
     * 79.5 is graded as 80. Null only when the scale has no bands.
     */
    public function bandFor(float $total): ?GradeBand
    {
        $score = (int) round($total, 0, PHP_ROUND_HALF_UP);

        return $this->bands->first(fn (GradeBand $band) => $score >= $band->min_score);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('grades')->logOnly(['level', 'name'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['level' => SchoolLevel::class];
    }
}
