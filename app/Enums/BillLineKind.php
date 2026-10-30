<?php

namespace App\Enums;

/**
 * A bill line is a charge (fee item) or a reduction (school discount or scholarship).
 */
enum BillLineKind: string
{
    case Fee = 'fee';
    case Discount = 'discount';
    case Scholarship = 'scholarship';

    public function isReduction(): bool
    {
        return $this !== self::Fee;
    }
}
