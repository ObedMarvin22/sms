<?php

namespace App\Models;

use App\Enums\ImportRowStatus;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\StudentImportRowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $student_import_id
 * @property int $row_number
 * @property array{student: array<string, string>, guardians: list<array<string, string>>} $data
 * @property ImportRowStatus $status
 * @property string|null $error
 * @property int|null $student_id
 */
class StudentImportRow extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StudentImportRowFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['student_import_id', 'row_number', 'data'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'pending'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'status' => ImportRowStatus::class,
        ];
    }
}
