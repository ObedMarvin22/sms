<?php

namespace App\Actions\Fees;

use App\Models\Student;
use App\Models\StudentDiscount;
use App\Models\Term;
use Illuminate\Validation\ValidationException;

/**
 * Gives a student a scholarship, or changes one: a name, an optional sponsor, a percentage or
 * fixed amount off the whole bill or chosen items, and optionally the last term it covers.
 */
class SaveScholarship
{
    /**
     * @param  array{name?: string, sponsor?: string|null, type?: string, value?: string, whole_bill?: bool, fee_item_ids?: array<int|string>, ends_term_id?: int|string|null}  $input
     */
    public function handle(Student $student, ?StudentDiscount $scholarship, array $input): StudentDiscount
    {
        if ($scholarship !== null && (! $scholarship->isScholarship() || $scholarship->student_id !== $student->id)) {
            throw ValidationException::withMessages(['name' => 'This is not one of the student\'s scholarships.']);
        }

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            throw ValidationException::withMessages(['name' => 'Enter a name of up to 100 characters, e.g. Headmaster\'s scholarship.']);
        }
        $sponsor = trim((string) ($input['sponsor'] ?? ''));
        if (mb_strlen($sponsor) > 100) {
            throw ValidationException::withMessages(['sponsor' => 'Keep the sponsor under 100 characters.']);
        }

        [$type, $value] = FeeRules::reduction((string) ($input['type'] ?? ''), (string) ($input['value'] ?? ''));
        $feeItemIds = FeeRules::feeItemIds((bool) ($input['whole_bill'] ?? true), $input['fee_item_ids'] ?? []);

        $endsTermId = (int) ($input['ends_term_id'] ?? 0);
        if ($endsTermId > 0 && ! Term::whereKey($endsTermId)->exists()) {
            throw ValidationException::withMessages(['ends_term_id' => 'Choose one of the school\'s terms.']);
        }

        $scholarship ??= new StudentDiscount(['student_id' => $student->id]);
        $scholarship->fill([
            'discount_id' => null,
            'name' => $name,
            'sponsor' => $sponsor === '' ? null : $sponsor,
            'type' => $type,
            'value' => $value,
            'fee_item_ids' => $feeItemIds,
            'ends_term_id' => $endsTermId > 0 ? $endsTermId : null,
        ])->save();

        return $scholarship;
    }
}
