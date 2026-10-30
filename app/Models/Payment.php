<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToSchool;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Money received for a student, with its receipt number. Only the reversal columns may ever
 * change (ReversePayment); deleting throws.
 *
 * @property int $id
 * @property int $student_id
 * @property string $receipt_number
 * @property Money $amount
 * @property PaymentMethod $method
 * @property string|null $reference
 * @property CarbonImmutable $paid_on
 * @property string|null $proof_path
 * @property string|null $note
 * @property int|null $recorded_by
 * @property CarbonImmutable|null $reversed_at
 * @property int|null $reversed_by
 * @property string|null $reversal_reason
 * @property CarbonImmutable $created_at
 * @property-read Student $student
 * @property-read User|null $recorder
 * @property-read User|null $reverser
 */
#[Fillable(['student_id', 'receipt_number', 'amount', 'method', 'reference', 'paid_on', 'proof_path', 'note', 'recorded_by'])]
class Payment extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    use LogsActivity;

    /** Columns a reversal sets; everything else is fixed once recorded. */
    private const REVERSAL_COLUMNS = ['reversed_at', 'reversed_by', 'reversal_reason', 'updated_at'];

    protected static function booted(): void
    {
        static::updating(function (Payment $payment): void {
            if (array_diff(array_keys($payment->getDirty()), self::REVERSAL_COLUMNS) !== []) {
                throw new LogicException('Payments are never edited. Reverse it and record it again.');
            }
        });
        static::deleting(fn () => throw new LogicException('Payments are never deleted. Reverse it instead.'));
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('payments')
            ->logOnly(['student_id', 'receipt_number', 'amount', 'method', 'reference', 'paid_on', 'reversed_at', 'reversed_by', 'reversal_reason'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'method' => PaymentMethod::class,
            'paid_on' => 'immutable_date',
            'reversed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
