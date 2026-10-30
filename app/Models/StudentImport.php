<?php

namespace App\Models;

use App\Enums\ImportRowStatus;
use App\Enums\StudentImportStatus;
use App\Models\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Database\Factories\StudentImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $file_name
 * @property StudentImportStatus $status
 * @property int $total_rows
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable $created_at
 * @property-read User|null $user
 */
#[Fillable(['file_name'])]
class StudentImport extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StudentImportFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'queued'];

    /**
     * @return HasMany<StudentImportRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(StudentImportRow::class)->orderBy('row_number');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Row counts by status, e.g. ['pending' => 120, 'imported' => 480, 'failed' => 0].
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = StudentImportRow::where('student_import_id', $this->id)
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect(ImportRowStatus::cases())
            ->mapWithKeys(fn (ImportRowStatus $s) => [$s->value => (int) ($counts[$s->value] ?? 0)])->all();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StudentImportStatus::class,
            'completed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
