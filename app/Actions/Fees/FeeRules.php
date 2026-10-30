<?php

namespace App\Actions\Fees;

use App\Enums\FeeCategory;
use App\Models\FeeItem;
use App\Support\Money\Money;
use App\Support\Settings\SchoolSettings;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Checks shared by the fee Actions. Queries are school-scoped.
 */
final class FeeRules
{
    /** GH₵1,000,000: far above any real school fee, so a slip of the keyboard is caught. */
    public const MAX_AMOUNT = 100_000_000;

    public static function name(string $name, ?FeeItem $ignore = null): string
    {
        $name = preg_replace('/\s+/', ' ', trim($name)) ?? '';

        if ($name === '' || mb_strlen($name) > 100) {
            throw ValidationException::withMessages(['name' => 'Enter a name of up to 100 characters.']);
        }
        if (FeeItem::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))->exists()) {
            throw ValidationException::withMessages(['name' => 'There is already a fee item with this name.']);
        }

        return $name;
    }

    public static function category(string $value): FeeCategory
    {
        return FeeCategory::tryFrom($value) ?? throw ValidationException::withMessages(['category' => 'Choose a category.']);
    }

    /**
     * A typed amount in cedis ("1,250.50"): more than zero, at most MAX_AMOUNT.
     */
    public static function amount(string $input, string $field): Money
    {
        try {
            $amount = Money::parse($input);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([$field => 'Enter an amount in cedis, like 1,250.50.']);
        }

        if (! $amount->isPositive() || $amount->pesewas > self::MAX_AMOUNT) {
            throw ValidationException::withMessages([$field => 'Enter an amount between GH₵0.01 and GH₵1,000,000.']);
        }

        return $amount;
    }

    public static function offersBoarding(): bool
    {
        return (bool) SchoolSettings::for(app(CurrentSchool::class)->require())->get('offers_boarding', false);
    }

    public static function requireBoarding(string $field = 'residence'): void
    {
        if (! self::offersBoarding()) {
            throw ValidationException::withMessages([$field => 'This school does not take boarders. Turn boarding on in School profile first.']);
        }
    }
}
