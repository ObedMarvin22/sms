<?php

namespace App\Models;

use App\Enums\FeeCategory;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\FeeItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Something the school charges for. Amounts are set per term, class and residence in fee
 * structures.
 *
 * @property int $id
 * @property string $name
 * @property FeeCategory $category
 * @property bool $is_optional
 * @property bool $is_active
 */
#[Fillable(['name', 'category', 'is_optional', 'is_active'])]
class FeeItem extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<FeeItemFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * Matches the column defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['is_optional' => false, 'is_active' => true];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('fees')
            ->logOnly(['name', 'category', 'is_optional', 'is_active'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => FeeCategory::class,
            'is_optional' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
