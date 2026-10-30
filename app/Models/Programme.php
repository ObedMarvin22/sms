<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\ProgrammeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An SHS programme (e.g. General Science) with core and elective subjects.
 *
 * @property int $id
 * @property string $name
 * @property-read Collection<int, Subject> $subjects
 */
#[Fillable(['name'])]
class Programme extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ProgrammeFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * @return BelongsToMany<Subject, $this, ProgrammeSubject, 'pivot'>
     */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class)
            ->using(ProgrammeSubject::class)
            ->withPivot(['id', 'type', 'school_id'])
            ->withTimestamps()
            ->orderBy('name');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('subjects')->logOnly(['name'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
