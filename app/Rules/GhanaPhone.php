<?php

namespace App\Rules;

use App\Support\Phone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A number Phone::normalise() accepts (024…, +233 24…, 23324…).
 */
class GhanaPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || Phone::normalise($value) === null) {
            $fail('Enter a Ghana phone number, like 024 412 3456.');
        }
    }
}
