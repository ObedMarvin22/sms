<?php

namespace App\Actions\Billing;

use App\Enums\BillLineKind;
use App\Enums\Residence;
use App\Models\Enrolment;
use App\Models\SchoolClass;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;

/**
 * A worked-out bill before it is saved (BillStudent's answer; IssueBill saves it).
 */
final readonly class BillDraft
{
    /**
     * @param  list<array{kind: BillLineKind, fee_item_id: int|null, discount_id: int|null, student_discount_id: int|null, description: string, amount: Money}>  $lines
     * @param  list<array{due_on: CarbonImmutable, amount: Money}>  $instalments
     */
    public function __construct(
        public Enrolment $enrolment,
        public SchoolClass $class,
        public Residence $residence,
        public array $lines,
        public array $instalments,
        public Money $gross,
        public Money $reductions,
        public Money $net,
        public Money $arrears,
    ) {}
}
