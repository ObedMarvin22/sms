<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\BelongsToSchool;
use App\Support\Money\Money;
use Database\Factories\FeeStructureItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One line of a fee structure: a fee item and its amount. Amount changes are logged.
 *
 * @property int $id
 * @property int $fee_structure_id
 * @property int $fee_item_id
 * @property Money $amount
 * @property-read FeeItem $feeItem
 * @property-read FeeStructure $structure
 */
#[Fillable(['fee_structure_id', 'fee_item_id', 'amount'])]
class FeeStructureItem extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<FeeStructureItemFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * @return BelongsTo<FeeItem, $this>
     */
    public function feeItem(): BelongsTo
    {
        return $this->belongsTo(FeeItem::class);
    }

    /**
     * @return BelongsTo<FeeStructure, $this>
     */
    public function structure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class, 'fee_structure_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('fees')
            ->logOnly(['fee_structure_id', 'fee_item_id', 'amount'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => MoneyCast::class];
    }
}
