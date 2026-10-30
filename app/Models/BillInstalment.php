<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\BelongsToSchool;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Database\Factories\BillInstalmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Part of a bill and when it is due.
 *
 * @property int $id
 * @property int $bill_id
 * @property int $sequence
 * @property CarbonImmutable $due_on
 * @property Money $amount
 */
#[Fillable(['bill_id', 'sequence', 'due_on', 'amount'])]
class BillInstalment extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<BillInstalmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['due_on' => 'immutable_date', 'amount' => MoneyCast::class];
    }
}
