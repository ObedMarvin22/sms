<?php

namespace App\Support\Assessment;

use App\Models\Enrolment;
use App\Models\Student;

/**
 * One student on a mark sheet. Students who left the class during the term stay on it, read-only.
 */
final readonly class SheetRow
{
    public function __construct(
        public Student $student,
        public Enrolment $enrolment,
        public bool $editable,
        public ?string $note = null,
    ) {}
}
