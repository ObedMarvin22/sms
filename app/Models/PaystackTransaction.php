<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\PaystackTransactionStatus;
use App\Models\Concerns\BelongsToSchool;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Database\Factories\PaystackTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One online payment attempt through Paystack.
 *
 * @property int $id
 * @property int $student_id
 * @property string $reference
 * @property Money $amount credited to the student
 * @property Money $charge Paystack's fee, when the school passes it on
 * @property Money $total what the parent pays
 * @property string $email
 * @property PaystackTransactionStatus $status
 * @property string|null $attention
 * @property int|null $payment_id
 * @property int|null $initiated_by
 * @property array<string, mixed>|null $gateway_response
 * @property CarbonImmutable|null $verified_at
 * @property CarbonImmutable $created_at
 * @property-read Student $student
 * @property-read Payment|null $payment
 */
#[Fillable(['student_id', 'reference', 'amount', 'charge', 'total', 'email', 'initiated_by'])]
class PaystackTransaction extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PaystackTransactionFactory> */
    use HasFactory;

    use LogsActivity;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'pending', 'charge' => 0];

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('payments')
            ->logOnly(['reference', 'amount', 'total', 'status', 'attention', 'payment_id'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'charge' => MoneyCast::class,
            'total' => MoneyCast::class,
            'status' => PaystackTransactionStatus::class,
            'gateway_response' => 'array',
            'verified_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
