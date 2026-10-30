<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\StreamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A stream such as "A" in "JHS 2A". Students are enrolled into a stream (Sprint 3).
 *
 * @property int $id
 * @property int $school_class_id
 * @property string $name
 * @property-read SchoolClass $schoolClass
 * @property-read string $full_name e.g. "JHS 2A"
 */
#[Fillable(['school_class_id', 'name'])]
class Stream extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StreamFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function getFullNameAttribute(): string
    {
        return $this->schoolClass->name.$this->name;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('classes')->logOnly(['school_class_id', 'name'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
