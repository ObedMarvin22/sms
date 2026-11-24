<?php

namespace App\Enums;

/**
 * A student's result on one task. Absent counts as zero; Exempt leaves the task out for them.
 */
enum ScoreStatus: string
{
    case Marked = 'marked';
    case Absent = 'absent';
    case Exempt = 'exempt';

    /**
     * What the teacher types (and sees) in a mark-sheet cell for this status.
     */
    public function key(): ?string
    {
        return match ($this) {
            self::Marked => null,
            self::Absent => 'A',
            self::Exempt => 'X',
        };
    }
}
