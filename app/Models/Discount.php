<?php

namespace App\Models;

use App\Enums\DiscountKind;
use App\Enums\DiscountType;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\DiscountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A discount the school offers (sibling, staff child or custom).
 *
 * @property int $id
 * @property string $name
 * @property DiscountKind $kind
 * @property DiscountType $type
 * @property int $value basis points (percent) or pesewas (fixed)
 * @property list<int>|null $fee_item_ids null = every item on the bill
 * @property bool $is_active
 */
#[Fillable(['name', 'kind', 'type', 'value', 'fee_item_ids', 'is_active'])]
class Discount extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<DiscountFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * Matches the column default.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['is_active' => true];

    /**
     * "10% of Tuition" or "GH₵200.00 off the bill".
     *
     * @param  array<int, string>  $itemNames  fee item id => name
     */
    public function describe(array $itemNames): string
    {
        return $this->type->describe($this->value, $this->fee_item_ids, $itemNames);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('fees')
            ->logOnly(['name', 'kind', 'type', 'value', 'fee_item_ids', 'is_active'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => DiscountKind::class,
            'type' => DiscountType::class,
            'value' => 'integer',
            'fee_item_ids' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
