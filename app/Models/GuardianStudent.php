<?php

namespace App\Models;

use App\Enums\GuardianAccess;
use App\Enums\GuardianRelationship;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\GuardianStudentFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A guardian's link to one child. A pivot model so it gets school_id (BelongsToSchool) and the
 * activity log. The Actions create and delete it as a model, never with attach()/detach(),
 * which would skip both.
 *
 * @property int $id
 * @property int $guardian_id
 * @property int $student_id
 * @property GuardianRelationship $relationship
 * @property GuardianAccess $access_level
 * @property bool $is_primary
 * @property bool $can_pick_up
 * @property-read Guardian $guardian
 * @property-read Student $student
 */
class GuardianStudent extends Pivot
{
    use BelongsToSchool;

    /** @use HasFactory<GuardianStudentFactory> */
    use HasFactory;

    use LogsActivity;

    public $incrementing = true;

    protected $table = 'guardian_student';

    /** @var list<string> */
    protected $fillable = ['guardian_id', 'student_id', 'relationship', 'access_level', 'can_pick_up'];

    /** @var array<string, mixed> */
    protected $attributes = ['access_level' => 'full', 'can_pick_up' => true];

    /**
     * @return BelongsTo<Guardian, $this>
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('guardians')
            ->logOnly(['guardian_id', 'student_id', 'relationship', 'access_level', 'is_primary', 'can_pick_up'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * Stored as true or NULL (never false): one primary contact per child (unique index).
     *
     * @return Attribute<bool, bool|null>
     */
    protected function isPrimary(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): bool => (bool) $value,
            set: fn (mixed $value): ?bool => $value ? true : null,
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'relationship' => GuardianRelationship::class,
            'access_level' => GuardianAccess::class,
            'can_pick_up' => 'boolean',
        ];
    }
}
