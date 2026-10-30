<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $admission_number
 * @property string $first_name
 * @property string $last_name
 * @property string|null $other_names
 * @property Gender|null $gender
 * @property CarbonImmutable|null $date_of_birth
 * @property CarbonImmutable $admitted_on
 * @property StudentStatus $status
 * @property-read string $full_name
 */
#[Fillable(['admission_number', 'first_name', 'last_name', 'other_names', 'gender', 'date_of_birth', 'admitted_on'])]
class Student extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    use LogsActivity;
    use SoftDeletes;

    /**
     * Matches the column default, so a freshly created model knows it is active.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'active'];

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.($this->other_names ? $this->other_names.' ' : '').$this->last_name);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('students')
            ->logOnly(['admission_number', 'first_name', 'last_name', 'other_names', 'gender', 'date_of_birth', 'status'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'status' => StudentStatus::class,
            'date_of_birth' => 'immutable_date',
            'admitted_on' => 'immutable_date',
        ];
    }
}
