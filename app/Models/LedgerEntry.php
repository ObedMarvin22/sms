<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\LedgerEntryType;
use App\Models\Concerns\BelongsToSchool;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Database\Factories\LedgerEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One line of a student's account. Append-only (SPEC section 8): saving changes to an existing
 * entry or deleting one throws. Balance = sum of amounts (positive = owed).
 *
 * @property int $id
 * @property int $student_id
 * @property LedgerEntryType $type
 * @property Money $amount
 * @property int|null $bill_id
 * @property string $description
 * @property CarbonImmutable $entry_date
 * @property int|null $created_by
 * @property CarbonImmutable $created_at
 */
#[Fillable(['student_id', 'type', 'amount', 'bill_id', 'description', 'entry_date', 'created_by'])]
class LedgerEntry extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<LedgerEntryFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Ledger entries are never changed. Add a reversing entry instead.'));
        static::deleting(fn () => throw new LogicException('Ledger entries are never deleted. Add a reversing entry instead.'));
    }

    /**
     * What the student owes now (negative: in credit).
     */
    public static function balanceFor(int $studentId): Money
    {
        return Money::pesewas((int) self::where('student_id', $studentId)->sum('amount'));
    }

    /**
     * @return BelongsTo<Bill, $this>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LedgerEntryType::class,
            'amount' => MoneyCast::class,
            'entry_date' => 'immutable_date',
            'created_at' => 'immutable_datetime',
        ];
    }
}
