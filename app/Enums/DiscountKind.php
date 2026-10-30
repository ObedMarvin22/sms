<?php

namespace App\Enums;

/**
 * Who a school discount is for. Sibling discounts are applied automatically at billing (2nd and
 * later children sharing a guardian); the others are given to students by hand.
 */
enum DiscountKind: string
{
    case Sibling = 'sibling';
    case StaffChild = 'staff_child';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Sibling => 'Sibling (automatic)',
            self::StaffChild => 'Staff child',
            self::Custom => 'Other',
        };
    }

    public function isAutomatic(): bool
    {
        return $this === self::Sibling;
    }
}
