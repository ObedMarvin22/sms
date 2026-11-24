<?php

namespace App\Support\Assessment;

use App\Enums\SheetProgress;
use App\Models\Stream;
use App\Models\Subject;

/**
 * One class and subject on the head's progress overview.
 */
final readonly class ProgressCell
{
    public function __construct(
        public Stream $stream,
        public Subject $subject,
        public SheetProgress $progress,
        public ?string $teacher,
        public ?int $teacherId,
        public int $filled,
        public int $expected,
    ) {}

    /**
     * Share of boxes filled, 0–100, for "in progress" sheets.
     */
    public function percent(): int
    {
        return $this->expected === 0 ? 0 : (int) floor($this->filled / $this->expected * 100);
    }
}
