<?php

namespace App\Enums;

/**
 * How an enrolment is going or how it ended.
 */
enum EnrolmentStatus: string
{
    case Active = 'active';
    case Promoted = 'promoted';
    case Repeated = 'repeated';
    case Moved = 'moved';
    case Withdrawn = 'withdrawn';
    case TransferredOut = 'transferred_out';
    case Graduated = 'graduated';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Current',
            self::Promoted => 'Promoted',
            self::Repeated => 'Repeated the class',
            self::Moved => 'Moved to another stream',
            self::Withdrawn => 'Withdrawn',
            self::TransferredOut => 'Transferred to another school',
            self::Graduated => 'Graduated',
        };
    }
}
