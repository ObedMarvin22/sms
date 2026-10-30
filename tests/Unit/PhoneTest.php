<?php

use App\Support\Phone;

it('normalises Ghanaian numbers to +233XXXXXXXXX', function (string $input) {
    expect(Phone::normalise($input))->toBe('+233244123456');
})->with([
    '0244123456',
    '244123456',
    '233244123456',
    '+233244123456',
    '00233244123456',
    '024 412 3456',
    '+233 24 412 3456',
    '(024) 412-3456',
    '024.412.3456',
]);

it('accepts landlines and the 5x mobile range', function () {
    expect(Phone::normalise('0302123456'))->toBe('+233302123456')
        ->and(Phone::normalise('0551234567'))->toBe('+233551234567');
});

it('rejects anything that is not a Ghanaian number', function (?string $input) {
    expect(Phone::normalise($input))->toBeNull();
})->with([
    null,
    '',
    '12345',
    '02441234567',     // one digit too many
    '+44 20 7946 0958',
    '0144123456',      // national number must start with 2, 3 or 5
    'not a number',
]);
