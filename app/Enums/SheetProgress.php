<?php

namespace App\Enums;

/**
 * How far a class's mark sheet for a subject has got, as the head's overview shows it.
 */
enum SheetProgress: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Ready = 'ready';
    case Submitted = 'submitted';
    case Reopened = 'reopened';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not started',
            self::InProgress => 'In progress',
            self::Ready => 'Ready to submit',
            self::Submitted => 'Submitted',
            self::Reopened => 'Reopened',
        };
    }

    /**
     * Still needs the teacher's attention ("show only missing").
     */
    public function isOutstanding(): bool
    {
        return $this !== self::Submitted;
    }
}
