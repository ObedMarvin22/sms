<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Database\Factories\InstalmentPlanStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One instalment: the share of the bill (basis points) due by a date.
 *
 * @property int $id
 * @property int $instalment_plan_id
 * @property int $sequence
 * @property CarbonImmutable $due_on
 * @property int $percent basis points (5000 = 50%)
 */
#[Fillable(['instalment_plan_id', 'sequence', 'due_on', 'percent'])]
class InstalmentPlanStep extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<InstalmentPlanStepFactory> */
    use HasFactory;

    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('fees')
            ->logOnly(['instalment_plan_id', 'sequence', 'due_on', 'percent'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['due_on' => 'immutable_date', 'percent' => 'integer'];
    }
}
