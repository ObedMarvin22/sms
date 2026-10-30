<?php

namespace App\Actions\Billing;

use App\Enums\Residence;
use App\Models\Bill;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\Term;
use App\Support\Money\Money;

/**
 * What "Bill students" would do for a term, cheaply (no bill is worked out): students to bill per
 * class and residence, whether each group has a fee structure, and an estimate before discounts
 * and optional items.
 */
class PreviewTermBilling
{
    /**
     * @return array{
     *     groups: list<array{class: string, residence: Residence, students: int, has_structure: bool, estimate: Money}>,
     *     to_bill: int, already_billed: int, missing_structures: int, estimate: Money
     * }
     */
    public function handle(Term $term): array
    {
        $students = TermBilling::unbilled($term)->with('currentEnrolment.stream.schoolClass')->get(['id', 'residence']);
        $structures = FeeStructure::with('lines.feeItem')->where('term_id', $term->id)->get()
            ->keyBy(fn (FeeStructure $s) => $s->school_class_id.'-'.$s->residence->value);

        /** @var array<string, array{class: string, sequence: int, residence: Residence, students: int, structure: FeeStructure|null}> $byGroup */
        $byGroup = [];
        foreach ($students as $student) {
            $class = $student->currentEnrolment?->stream->schoolClass;
            if ($class === null) {
                continue;
            }
            $key = $class->id.'-'.$student->residence->value;
            $byGroup[$key] ??= ['class' => $class->name, 'sequence' => $class->sequence, 'residence' => $student->residence, 'students' => 0, 'structure' => $structures->get($key)];
            $byGroup[$key]['students']++;
        }
        uasort($byGroup, fn (array $a, array $b) => [$a['sequence'], $a['residence']->value] <=> [$b['sequence'], $b['residence']->value]);

        $groups = [];
        $missing = 0;
        foreach ($byGroup as $group) {
            $compulsory = $group['structure'] === null ? Money::zero() : Money::sum($group['structure']->lines
                ->filter(fn (FeeStructureItem $line) => ! $line->feeItem->is_optional)->map(fn (FeeStructureItem $line) => $line->amount));
            $groups[] = [
                'class' => $group['class'],
                'residence' => $group['residence'],
                'students' => $group['students'],
                'has_structure' => $group['structure'] !== null,
                'estimate' => Money::pesewas($compulsory->pesewas * $group['students']),
            ];
            $missing += $group['structure'] === null ? $group['students'] : 0;
        }

        return [
            'groups' => $groups,
            'to_bill' => $students->count(),
            'already_billed' => Bill::where('term_id', $term->id)->count(),
            'missing_structures' => $missing,
            'estimate' => Money::sum(array_column($groups, 'estimate')),
        ];
    }
}
