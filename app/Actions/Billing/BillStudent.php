<?php

namespace App\Actions\Billing;

use App\Enums\BillLineKind;
use App\Enums\DiscountKind;
use App\Enums\DiscountType;
use App\Enums\FeeCategory;
use App\Enums\StudentStatus;
use App\Models\Discount;
use App\Models\Enrolment;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\GuardianStudent;
use App\Models\InstalmentPlan;
use App\Models\LedgerEntry;
use App\Models\Student;
use App\Models\StudentDiscount;
use App\Models\StudentFeeOption;
use App\Models\StudentInstalmentPlan;
use App\Models\Term;
use App\Support\Money\Money;
use App\Support\Money\Percent;
use Carbon\CarbonImmutable;

/**
 * Works out a student's bill for a term without saving it. The money rules live here:
 *
 * - Charges: every compulsory line of the student's fee structure (term × class × residence),
 *   plus optional lines the student opted into.
 * - Reductions, in order: the sibling discount (2nd and later children sharing a guardian, by
 *   admission date), hand-given discounts, then scholarships still running in this term. Each is
 *   worked out on the full price of the items it covers, rounded to the pesewa (half up), and
 *   never takes an item below zero. Each becomes its own negative line.
 * - Instalments from the student's plan, else the term's default plan, else one payment due on
 *   the first day of term. They add up to the net exactly (the last takes any remainder).
 * - Arrears: the ledger balance before this bill, for information.
 *
 * @throws StudentNotBillable
 */
class BillStudent
{
    public function handle(Student $student, Term $term): BillDraft
    {
        if ($student->status !== StudentStatus::Active) {
            throw new StudentNotBillable('Not an active student.');
        }

        $enrolment = Enrolment::with('stream.schoolClass')->where('student_id', $student->id)
            ->where('is_current', true)->where('academic_year_id', $term->academic_year_id)->first();
        if ($enrolment === null) {
            throw new StudentNotBillable('Not enrolled in this academic year.');
        }
        $class = $enrolment->stream->schoolClass;

        $structure = FeeStructure::with('lines.feeItem')->where('term_id', $term->id)
            ->where('school_class_id', $class->id)->where('residence', $student->residence->value)->first();
        if ($structure === null) {
            throw new StudentNotBillable("No {$class->name} ({$student->residence->label()}) fee structure for {$term->name}.");
        }

        $optedIn = array_map(intval(...), StudentFeeOption::where('student_id', $student->id)->pluck('fee_item_id')->all());
        $charges = $structure->lines
            ->filter(fn (FeeStructureItem $line) => ! $line->feeItem->is_optional || in_array($line->fee_item_id, $optedIn, true))
            // Compulsory before optional, then category order (tuition first), then name.
            ->sortBy(fn (FeeStructureItem $line) => [
                $line->feeItem->is_optional,
                array_search($line->feeItem->category, FeeCategory::cases(), true),
                $line->feeItem->name,
            ])
            ->values();
        if ($charges->isEmpty()) {
            throw new StudentNotBillable('Nothing to bill: the fee structure has no items for this student.');
        }

        $lines = [];
        $remaining = [];
        foreach ($charges as $i => $charge) {
            $lines[] = $this->line(BillLineKind::Fee, $charge->feeItem->name, $charge->amount, feeItemId: $charge->fee_item_id);
            $remaining[$i] = $charge->amount->pesewas;
        }
        $gross = Money::sum($charges->map(fn (FeeStructureItem $c) => $c->amount));

        $taken = 0;
        foreach ($this->reductions($student, $term) as $reduction) {
            $amount = $this->apply($reduction, $charges->all(), $remaining);
            if ($amount > 0) {
                $lines[] = $this->line($reduction['kind'], $reduction['description'], Money::pesewas(-$amount),
                    discountId: $reduction['discount_id'], studentDiscountId: $reduction['student_discount_id']);
                $taken += $amount;
            }
        }

        $net = $gross->minus(Money::pesewas($taken));

        return new BillDraft(
            enrolment: $enrolment,
            class: $class,
            residence: $student->residence,
            lines: $lines,
            instalments: $this->instalments($student, $term, $net),
            gross: $gross,
            reductions: Money::pesewas($taken),
            net: $net,
            arrears: LedgerEntry::balanceFor($student->id),
        );
    }

    /**
     * Reductions this student gets this term, in the order they are applied.
     *
     * @return list<array{kind: BillLineKind, description: string, type: DiscountType, value: int, fee_item_ids: list<int>|null, discount_id: int|null, student_discount_id: int|null}>
     */
    private function reductions(Student $student, Term $term): array
    {
        $reductions = [];

        $sibling = Discount::where('kind', DiscountKind::Sibling->value)->where('is_active', true)->first();
        if ($sibling !== null && $this->hasOlderSibling($student)) {
            $reductions[] = [
                'kind' => BillLineKind::Discount, 'description' => $sibling->name, 'type' => $sibling->type, 'value' => $sibling->value,
                'fee_item_ids' => $sibling->fee_item_ids, 'discount_id' => $sibling->id, 'student_discount_id' => null,
            ];
        }

        $given = StudentDiscount::with(['discount', 'endsTerm'])->where('student_id', $student->id)->get();

        foreach ($given->filter(fn (StudentDiscount $d) => $d->discount?->is_active === true)->sortBy(fn (StudentDiscount $d) => $d->discount?->name) as $link) {
            $discount = $link->discount;
            if ($discount === null) {
                continue;
            }
            $reductions[] = [
                'kind' => BillLineKind::Discount, 'description' => $discount->name, 'type' => $discount->type, 'value' => $discount->value,
                'fee_item_ids' => $discount->fee_item_ids, 'discount_id' => $discount->id, 'student_discount_id' => $link->id,
            ];
        }

        $running = $given->filter(fn (StudentDiscount $d) => $d->isScholarship()
            && ($d->endsTerm === null || $d->endsTerm->starts_on->gte($term->starts_on)));
        foreach ($running->sortBy('name') as $scholarship) {
            if ($scholarship->type === null) {
                continue;
            }
            $reductions[] = [
                'kind' => BillLineKind::Scholarship,
                'description' => mb_substr($scholarship->name.($scholarship->sponsor ? " ({$scholarship->sponsor})" : ''), 0, 150),
                'type' => $scholarship->type, 'value' => (int) $scholarship->value,
                'fee_item_ids' => $scholarship->fee_item_ids, 'discount_id' => null, 'student_discount_id' => $scholarship->id,
            ];
        }

        return $reductions;
    }

    /**
     * Takes a reduction off the lines it covers, never below zero. Percentages are worked out on
     * each line's full price; fixed amounts are taken line by line in bill order.
     *
     * @param  array{type: DiscountType, value: int, fee_item_ids: list<int>|null}  $reduction
     * @param  array<int, FeeStructureItem>  $charges  keyed like $remaining
     * @param  array<int, int>  $remaining  pesewas still chargeable per line (updated)
     * @return int pesewas taken
     */
    private function apply(array $reduction, array $charges, array &$remaining): int
    {
        $taken = 0;
        $fixedLeft = $reduction['value'];

        foreach ($charges as $i => $charge) {
            if ($reduction['fee_item_ids'] !== null && ! in_array($charge->fee_item_id, $reduction['fee_item_ids'], true)) {
                continue;
            }

            $want = $reduction['type'] === DiscountType::Percent
                ? self::percentOf($charge->amount->pesewas, $reduction['value'])
                : $fixedLeft;
            $take = min($want, $remaining[$i]);

            $remaining[$i] -= $take;
            $taken += $take;
            if ($reduction['type'] === DiscountType::Fixed) {
                $fixedLeft -= $take;
            }
        }

        return $taken;
    }

    /**
     * @return list<array{due_on: CarbonImmutable, amount: Money}>
     */
    private function instalments(Student $student, Term $term, Money $net): array
    {
        $plan = StudentInstalmentPlan::with('plan.steps')->where('student_id', $student->id)->where('term_id', $term->id)->first()->plan
            ?? InstalmentPlan::with('steps')->where('term_id', $term->id)->where('is_default', true)->first();

        if ($plan === null || $plan->steps->isEmpty()) {
            return [['due_on' => $term->starts_on, 'amount' => $net]];
        }

        $instalments = [];
        $left = $net->pesewas;
        $last = $plan->steps->count() - 1;
        foreach ($plan->steps->values() as $i => $step) {
            $amount = $i === $last ? $left : min($left, self::percentOf($net->pesewas, $step->percent));
            $left -= $amount;
            $instalments[] = ['due_on' => $step->due_on, 'amount' => Money::pesewas($amount)];
        }

        return $instalments;
    }

    /**
     * Whether a brother or sister (a child sharing any guardian) was admitted before this
     * student and is still an active, enrolled student: then this student is the 2nd or later.
     */
    private function hasOlderSibling(Student $student): bool
    {
        $guardianIds = GuardianStudent::where('student_id', $student->id)->pluck('guardian_id');
        if ($guardianIds->isEmpty()) {
            return false;
        }

        $siblingIds = GuardianStudent::whereIn('guardian_id', $guardianIds)->where('student_id', '!=', $student->id)->pluck('student_id');

        return Student::whereIn('id', $siblingIds)
            ->where('status', StudentStatus::Active->value)
            ->whereHas('currentEnrolment')
            ->where(fn ($q) => $q->where('admitted_on', '<', $student->admitted_on->toDateString())
                ->orWhere(fn ($q) => $q->where('admitted_on', $student->admitted_on->toDateString())->where('id', '<', $student->id)))
            ->exists();
    }

    /**
     * A share of an amount in basis points, rounded to the nearest pesewa (half up).
     */
    public static function percentOf(int $pesewas, int $basisPoints): int
    {
        return intdiv($pesewas * $basisPoints + Percent::HUNDRED / 2, Percent::HUNDRED);
    }

    /**
     * @return array{kind: BillLineKind, fee_item_id: int|null, discount_id: int|null, student_discount_id: int|null, description: string, amount: Money}
     */
    private function line(BillLineKind $kind, string $description, Money $amount, ?int $feeItemId = null, ?int $discountId = null, ?int $studentDiscountId = null): array
    {
        return [
            'kind' => $kind,
            'fee_item_id' => $feeItemId,
            'discount_id' => $discountId,
            'student_discount_id' => $studentDiscountId,
            'description' => mb_substr($description, 0, 150),
            'amount' => $amount,
        ];
    }
}
