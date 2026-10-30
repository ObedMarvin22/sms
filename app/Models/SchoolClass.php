<?php

namespace App\Models;

use App\Enums\SchoolLevel;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\SchoolClassFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A class such as "JHS 2" ("class" is reserved in PHP, hence SchoolClass / school_classes).
 *
 * @property int $id
 * @property SchoolLevel $level
 * @property string $name
 * @property int $sequence
 * @property-read Collection<int, Stream> $streams
 */
#[Fillable(['level', 'name', 'sequence'])]
class SchoolClass extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SchoolClassFactory> */
    use HasFactory;

    use LogsActivity;

    protected $table = 'school_classes';

    /**
     * @return HasMany<Stream, $this>
     */
    public function streams(): HasMany
    {
        return $this->hasMany(Stream::class)->orderBy('name');
    }

    /**
     * @return BelongsToMany<Subject, $this, ClassSubject, 'pivot'>
     */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'class_subject')
            ->using(ClassSubject::class)
            ->withPivot(['id', 'school_id'])
            ->withTimestamps()
            ->orderBy('name');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('classes')->logOnly(['level', 'name'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['level' => SchoolLevel::class];
    }
}
