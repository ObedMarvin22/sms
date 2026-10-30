<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\StaffStatus;
use App\Models\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Database\Factories\StaffFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Someone who works at the school. user_id is null when they have no login.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $staff_number
 * @property string $first_name
 * @property string $last_name
 * @property string|null $other_names
 * @property Gender|null $gender
 * @property string|null $phone +233XXXXXXXXX
 * @property string|null $email
 * @property string|null $job_title
 * @property CarbonImmutable|null $employed_on
 * @property StaffStatus $status
 * @property-read string $full_name
 * @property-read User|null $user
 */
#[Fillable(['staff_number', 'first_name', 'last_name', 'other_names', 'gender', 'phone', 'email', 'job_title', 'employed_on'])]
class Staff extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StaffFactory> */
    use HasFactory;

    use LogsActivity;
    use SoftDeletes;

    protected $table = 'staff';

    /**
     * Matches the column default, so a freshly created model knows it is active.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'active'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<TeachingAssignment, $this>
     */
    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.($this->other_names ? $this->other_names.' ' : '').$this->last_name);
    }

    public function isActive(): bool
    {
        return $this->status === StaffStatus::Active;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('staff')
            ->logOnly(['staff_number', 'first_name', 'last_name', 'other_names', 'phone', 'email', 'job_title', 'status', 'user_id'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'status' => StaffStatus::class,
            'employed_on' => 'immutable_date',
        ];
    }
}
