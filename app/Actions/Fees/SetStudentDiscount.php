<?php

namespace App\Actions\Fees;

use App\Models\Discount;
use App\Models\Student;
use App\Models\StudentDiscount;
use Illuminate\Validation\ValidationException;

/**
 * Gives a student a staff-child or custom discount, or takes it away. Logged. Sibling discounts
 * are never given by hand: billing works them out from guardian links.
 */
class SetStudentDiscount
{
    public function handle(Student $student, Discount $discount, bool $gives): void
    {
        $link = StudentDiscount::where('student_id', $student->id)->where('discount_id', $discount->id)->first();

        if (! $gives) {
            $link?->delete();

            return;
        }

        if ($discount->kind->isAutomatic()) {
            throw ValidationException::withMessages(['discounts' => 'The sibling discount is applied automatically at billing.']);
        }
        if (! $discount->is_active) {
            throw ValidationException::withMessages(['discounts' => "{$discount->name} is no longer in use."]);
        }

        $link ?? StudentDiscount::create(['student_id' => $student->id, 'discount_id' => $discount->id]);
    }
}
