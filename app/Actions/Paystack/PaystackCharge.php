<?php

namespace App\Actions\Paystack;

use App\Support\Money\Money;
use App\Support\Money\Percent;
use App\Support\Settings\SchoolSettings;
use App\Support\Tenancy\CurrentSchool;

/**
 * Who pays Paystack's charge, and how much a parent pays when the school passes it on.
 */
final class PaystackCharge
{
    public const SCHOOL = 'school';

    public const PARENT = 'parent';

    public static function payer(): string
    {
        $payer = (string) SchoolSettings::for(app(CurrentSchool::class)->require())->get('paystack_charge_payer', self::SCHOOL);

        return $payer === self::PARENT ? self::PARENT : self::SCHOOL;
    }

    /**
     * What the parent pays so the school still receives $amount after Paystack takes its
     * percentage of the whole: amount / (1 - rate), rounded up to the pesewa.
     */
    public static function grossUp(Money $amount, int $basisPoints): Money
    {
        $keep = Percent::HUNDRED - $basisPoints;

        return Money::pesewas(intdiv($amount->pesewas * Percent::HUNDRED + $keep - 1, $keep));
    }

    public static function basisPoints(): int
    {
        return max(0, min(1_000, (int) config('services.paystack.charge_basis_points', 195)));
    }
}
