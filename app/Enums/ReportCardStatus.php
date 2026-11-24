<?php

namespace App\Enums;

/**
 * Where a student's report card stands. Results are compiled (draft), the PDF is made
 * (generated), the head approves the class (approved) and publishes it to parents (published).
 */
enum ReportCardStatus: string
{
    case Draft = 'draft';
    case Generated = 'generated';
    case Approved = 'approved';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Generated => 'Ready to approve',
            self::Approved => 'Approved',
            self::Published => 'Published',
        };
    }
}
