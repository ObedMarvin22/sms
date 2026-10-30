<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\BillLineKind;
use App\Models\Concerns\BelongsToSchool;
use App\Support\Money\Money;
use Database\Factories\BillLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One line of a bill: a fee (positive) or a discount / scholarship (negative).
 *
 * @property int $id
 * @property int $bill_id
 * @property int $sequence
 * @property BillLineKind $kind
 * @property int|null $fee_item_id
 * @property int|null $discount_id
 * @property int|null $student_discount_id
 * @property string $description
 * @property Money $amount
 */
#[Fillable(['bill_id', 'sequence', 'kind', 'fee_item_id', 'discount_id', 'student_discount_id', 'description', 'amount'])]
class BillLine extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<BillLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => BillLineKind::class, 'amount' => MoneyCast::class];
    }
}
