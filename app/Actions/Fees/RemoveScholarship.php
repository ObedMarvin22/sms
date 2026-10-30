<?php

namespace App\Actions\Fees;

use App\Models\StudentDiscount;
use Illuminate\Validation\ValidationException;

/**
 * Ends a scholarship now (logged). Bills already issued keep it.
 */
class RemoveScholarship
{
    public function handle(StudentDiscount $scholarship): void
    {
        if (! $scholarship->isScholarship()) {
            throw ValidationException::withMessages(['scholarship' => 'This is a school discount, not a scholarship.']);
        }

        $scholarship->delete();
    }
}
