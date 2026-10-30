<?php

use App\Support\Money\Money;

it('reads typed amounts as whole pesewas', function (string $input, int $pesewas) {
    expect(Money::parse($input)->pesewas)->toBe($pesewas);
})->with([
    ['1250', 125_000],
    ['1,250.50', 125_050],
    ['1250.5', 125_050],
    ['0.05', 5],
    [' GH₵ 80 ', 8_000],
    ['₵1,000,000.00', 100_000_000],
    ['ghs12.30', 1_230],
]);

it('refuses text that is not an amount', function (string $input) {
    expect(fn () => Money::parse($input))->toThrow(InvalidArgumentException::class);
})->with(['', 'abc', '12.345', '-5', '1.2.3', '12e3', '5 cedis', '.50']);

it('adds without float drift', function () {
    // 0.1 + 0.2 is 0.30000000000000004 as floats; in pesewas it is exact.
    expect(Money::parse('0.10')->plus(Money::parse('0.20'))->equals(Money::parse('0.30')))->toBeTrue()
        ->and(Money::sum(array_fill(0, 3, Money::parse('333.33')))->pesewas)->toBe(99_999)
        ->and(Money::parse('10')->minus(Money::parse('12.5'))->pesewas)->toBe(-250);
});

it('formats for people and for forms', function () {
    expect(Money::pesewas(125_050)->format())->toBe('GH₵1,250.50')
        ->and(Money::pesewas(5)->format())->toBe('GH₵0.05')
        ->and(Money::pesewas(-2_000)->format())->toBe('-GH₵20.00')
        ->and(Money::pesewas(125_050)->plain())->toBe('1,250.50')
        ->and(json_encode(['amount' => Money::pesewas(125_050)]))->toBe('{"amount":125050}');
});
