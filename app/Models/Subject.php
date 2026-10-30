<?php

namespace App\Models;

use App\Enums\SchoolLevel;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A subject, taught at one or more levels (e.g. English Language: Primary, JHS, SHS).
 *
 * @property int $id
 * @property string $name
 * @property string|null $code
 * @property Collection<int, SchoolLevel> $levels
 */
#[Fillable(['name', 'code', 'levels'])]
class Subject extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SubjectFactory> */
    use HasFactory;

    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('subjects')->logOnly(['name', 'code', 'levels'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['levels' => AsEnumCollection::of(SchoolLevel::class)];
    }
}
