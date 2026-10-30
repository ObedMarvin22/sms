<?php

use App\Support\Money\AmountInWords;
use App\Support\Money\Money;

it('writes amounts out for receipts', function (int $pesewas, string $words) {
    expect(AmountInWords::for(Money::pesewas($pesewas)))->toBe($words);
})->with([
    [110_050, 'One thousand one hundred Ghana cedis and fifty pesewas'],
    [100, 'One Ghana cedi'],
    [1, 'One pesewa'],
    [50, 'Fifty pesewas'],
    [0, 'Zero Ghana cedis'],
    [10_500, 'One hundred and five Ghana cedis'],
    [100_500, 'One thousand and five Ghana cedis'],
    [2_119_900, 'Twenty-one thousand one hundred and ninety-nine Ghana cedis'],
    [1_500_000_00, 'One million five hundred thousand Ghana cedis'],
    [99_999_999_999, 'Nine hundred and ninety-nine million nine hundred and ninety-nine thousand nine hundred and ninety-nine Ghana cedis and ninety-nine pesewas'],
    [-2_000, 'Minus twenty Ghana cedis'],
]);
