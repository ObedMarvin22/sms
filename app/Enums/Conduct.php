<?php

namespace App\Enums;

/**
 * The class teacher's word on a child's conduct for the term, as Ghanaian report cards print it.
 */
enum Conduct: string
{
    case Excellent = 'excellent';
    case VeryGood = 'very_good';
    case Good = 'good';
    case Fair = 'fair';
    case NeedsImprovement = 'needs_improvement';

    public function label(): string
    {
        return match ($this) {
            self::Excellent => 'Excellent',
            self::VeryGood => 'Very good',
            self::Good => 'Good',
            self::Fair => 'Fair',
            self::NeedsImprovement => 'Needs improvement',
        };
    }
}
