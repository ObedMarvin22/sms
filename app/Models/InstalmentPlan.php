<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\InstalmentPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * How a term's fees may be paid in parts. Its steps add up to 100%.
 *
 * @property int $id
 * @property int $term_id
 * @property string $name
 * @property bool $is_default
 * @property-read Term $term
 * @property-read Collection<int, InstalmentPlanStep> $steps
 */
#[Fillable(['term_id', 'name', 'is_default'])]
class InstalmentPlan extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<InstalmentPlanFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * @return HasMany<InstalmentPlanStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(InstalmentPlanStep::class)->orderBy('sequence');
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
        return LogOptions::defaults()->useLogName('fees')
            ->logOnly(['term_id', 'name', 'is_default'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * Stored as true or NULL (never false): one default plan per term (unique index).
     *
     * @return Attribute<bool, bool|null>
     */
    protected function isDefault(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): bool => (bool) $value,
            set: fn (mixed $value): ?bool => $value ? true : null,
        );
    }
}
