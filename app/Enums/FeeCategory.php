<?php

namespace App\Enums;

/**
 * What a fee item is for. Used to group bills and reports, and for discounts that apply to
 * tuition only.
 */
enum FeeCategory: string
{
    case Tuition = 'tuition';
    case Levy = 'levy';
    case Feeding = 'feeding';
    case Boarding = 'boarding';
    case Transport = 'transport';
    case Books = 'books';
    case Uniform = 'uniform';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Tuition => 'Tuition',
            self::Levy => 'Levies and dues (PTA, sports)',
            self::Feeding => 'Feeding',
            self::Boarding => 'Boarding',
            self::Transport => 'Transport',
            self::Books => 'Books and stationery',
            self::Uniform => 'Uniform',
            self::Other => 'Other',
        };
    }
}
