<?php

namespace App\Enums;

/**
 * How a level is assessed: marks in weighted parts, or teachers' comments only.
 */
enum AssessmentMode: string
{
    case Weighted = 'weighted';
    case Descriptive = 'descriptive';

    public function label(): string
    {
        return match ($this) {
            self::Weighted => 'Marks, weighted',
            self::Descriptive => 'Comments only',
        };
    }

    /**
     * Creche, Nursery and KG are described, not marked (Obed's choice, Sprint 5).
     */
    public static function defaultFor(SchoolLevel $level): self
    {
        return in_array($level, [SchoolLevel::Creche, SchoolLevel::Nursery, SchoolLevel::Kg], true) ? self::Descriptive : self::Weighted;
    }
}
