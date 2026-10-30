<?php

namespace App\Models;

use App\Enums\Residence;
use App\Models\Concerns\BelongsToSchool;
use App\Support\Money\Money;
use Database\Factories\FeeStructureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * What one class pays in one term, as day students or as boarders.
 *
 * @property int $id
 * @property int $term_id
 * @property int $school_class_id
 * @property Residence $residence
 * @property-read Term $term
 * @property-read SchoolClass $schoolClass
 * @property-read Collection<int, FeeStructureItem> $lines
 */
#[Fillable(['term_id', 'school_class_id', 'residence'])]
class FeeStructure extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<FeeStructureFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * @return HasMany<FeeStructureItem, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(FeeStructureItem::class);
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /**
     * Every line, optional items included (each student pays only the optional ones they take).
     */
    public function total(): Money
    {
        return Money::sum($this->lines->map(fn (FeeStructureItem $line): Money => $line->amount));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('fees')
            ->logOnly(['term_id', 'school_class_id', 'residence'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['residence' => Residence::class];
    }
}
