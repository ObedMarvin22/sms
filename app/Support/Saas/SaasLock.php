<?php

namespace App\Support\Saas;

use App\Models\SaasInvoice;
use App\Models\School;
use Carbon\CarbonImmutable;

/**
 * Whether a school's staff screens are read-only for an unpaid Orange Tree invoice (SPEC
 * section 8; Pilot preparation step 4). Worked out from the invoices, not stored:
 *
 * - open: nothing overdue;
 * - warning: an invoice is past its due date; the lock starts on `locksOn` unless it is paid
 *   (or Orange Tree keeps the school open until a date);
 * - locked: an invoice is still unpaid SAAS_GRACE_DAYS after its due date.
 *
 * Paying or voiding the invoice reopens the school at once.
 */
final class SaasLock
{
    public const OPEN = 'open';

    public const WARNING = 'warning';

    public const LOCKED = 'locked';

    /**
     * @param  self::OPEN|self::WARNING|self::LOCKED  $status
     */
    private function __construct(
        public readonly string $status,
        public readonly ?SaasInvoice $invoice = null,
        public readonly ?CarbonImmutable $locksOn = null,
        public readonly ?CarbonImmutable $keptOpenUntil = null,
    ) {}

    public static function for(School $school): self
    {
        // The oldest invoice that is past due and not fully paid.
        $invoice = SaasInvoice::live()->withSum('payments', 'amount')
            ->where('school_id', $school->id)
            ->where('due_on', '<', today()->toDateString())
            ->orderBy('due_on')->orderBy('id')
            ->get()
            ->first(fn (SaasInvoice $invoice) => $invoice->balance()->isPositive());

        if ($invoice === null) {
            return new self(self::OPEN);
        }

        $locksOn = $invoice->due_on->addDays(self::graceDays() + 1);
        $override = $school->saas_lock_override_until;

        if ($override !== null && ! $override->isBefore(today())) {
            return new self(self::WARNING, $invoice, $override->addDay()->max($locksOn), $override);
        }

        return today()->isBefore($locksOn)
            ? new self(self::WARNING, $invoice, $locksOn)
            : new self(self::LOCKED, $invoice, $locksOn);
    }

    public static function graceDays(): int
    {
        return max(0, (int) config('saas.grace_days'));
    }

    public function isLocked(): bool
    {
        return $this->status === self::LOCKED;
    }
}
