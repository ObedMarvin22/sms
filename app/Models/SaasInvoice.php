<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\SaasInvoiceStatus;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Database\Factories\SaasInvoiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Orange Tree's invoice to a school for one term: active students on the census date × rate.
 * A platform model: it has school_id but no school scope, because only the console reads it.
 *
 * @property int $id
 * @property string $number
 * @property int $school_id
 * @property int $term_id
 * @property int|null $plan_id
 * @property string $plan_name
 * @property Money $rate_per_student
 * @property CarbonImmutable $census_on
 * @property int $student_count
 * @property Money $amount
 * @property CarbonImmutable $issued_at
 * @property CarbonImmutable $due_on
 * @property int|null $issued_by
 * @property CarbonImmutable|null $voided_at
 * @property string|null $void_reason
 * @property int|null $voided_by
 * @property int|null $payments_sum_amount from withSum('payments', 'amount')
 * @property-read School $school
 * @property-read Term $term
 * @property-read Collection<int, SaasPayment> $payments
 */
class SaasInvoice extends Model
{
    /** @use HasFactory<SaasInvoiceFactory> */
    use HasFactory;

    use LogsActivity;

    /** @var list<string> */
    protected $guarded = ['*'];

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * The school's term. Its school scope is lifted: the console has no current school, and the
     * census command may look at invoices of the school it is running for only.
     *
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        // Platform code: the invoice already names its school, so the term needs no school filter.
        return $this->belongsTo(Term::class)->withoutGlobalScopes();
    }

    /**
     * @return HasMany<SaasPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SaasPayment::class)->orderBy('paid_on')->orderBy('id');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->whereNull('voided_at');
    }

    /**
     * Filters by status in SQL (status() works it out per invoice; this does the same in a query).
     *
     * @param  Builder<self>  $query
     */
    public function scopeWhereStatus(Builder $query, SaasInvoiceStatus $status): void
    {
        $paid = '(SELECT COALESCE(SUM(amount), 0) FROM saas_payments WHERE saas_payments.saas_invoice_id = saas_invoices.id)';

        match ($status) {
            SaasInvoiceStatus::Void => $query->whereNotNull('voided_at'),
            SaasInvoiceStatus::Paid => $query->whereNull('voided_at')->whereRaw("saas_invoices.amount <= {$paid}"),
            SaasInvoiceStatus::Overdue => $query->whereNull('voided_at')->whereRaw("saas_invoices.amount > {$paid}")->where('due_on', '<', today()->toDateString()),
            SaasInvoiceStatus::Unpaid => $query->whereNull('voided_at')->whereRaw("saas_invoices.amount > {$paid}")->where('due_on', '>=', today()->toDateString()),
        };
    }

    public function paid(): Money
    {
        return Money::pesewas((int) ($this->payments_sum_amount ?? $this->payments()->sum('amount')));
    }

    public function balance(): Money
    {
        return $this->amount->minus($this->paid());
    }

    public function status(): SaasInvoiceStatus
    {
        return match (true) {
            $this->voided_at !== null => SaasInvoiceStatus::Void,
            ! $this->balance()->isPositive() => SaasInvoiceStatus::Paid,
            $this->due_on->isBefore(today()) => SaasInvoiceStatus::Overdue,
            default => SaasInvoiceStatus::Unpaid,
        };
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('saas')
            ->logOnly(['number', 'school_id', 'student_count', 'amount', 'voided_at', 'void_reason'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate_per_student' => MoneyCast::class,
            'amount' => MoneyCast::class,
            'census_on' => 'immutable_date',
            'issued_at' => 'immutable_datetime',
            'due_on' => 'immutable_date',
            'voided_at' => 'immutable_datetime',
            'student_count' => 'integer',
        ];
    }
}
