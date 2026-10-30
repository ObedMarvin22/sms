<?php

namespace App\Actions\Fees;

use App\Models\FeeItem;
use App\Models\Student;
use App\Models\StudentFeeOption;
use Illuminate\Validation\ValidationException;

/**
 * Adds or removes an optional fee item (transport, feeding, ...) for a student. Logged.
 */
class SetFeeOption
{
    public function handle(Student $student, FeeItem $item, bool $takes): void
    {
        $option = StudentFeeOption::where('student_id', $student->id)->where('fee_item_id', $item->id)->first();

        if (! $takes) {
            $option?->delete(); // one model delete, so the removal is logged

            return;
        }

        if (! $item->is_optional || ! $item->is_active) {
            throw ValidationException::withMessages(['options' => "{$item->name} is not an optional item students can choose."]);
        }

        $option ?? StudentFeeOption::create(['student_id' => $student->id, 'fee_item_id' => $item->id]);
    }
}
