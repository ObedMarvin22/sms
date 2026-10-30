<?php

use App\Models\School;
use App\Support\Branding\SchoolTheme;

it('picks white text on dark colours and black text on light ones', function (string $background, string $text) {
    expect(SchoolTheme::textColourOn($background))->toBe($text);
})->with([
    'dark green' => ['#0a7d3c', '#ffffff'],
    'navy' => ['#1e3a8a', '#ffffff'],
    'black' => ['#000000', '#ffffff'],
    'yellow' => ['#f5d90a', '#000000'],
    'white' => ['#ffffff', '#000000'],
    'light grey' => ['#e5e5e5', '#000000'],
]);

it('only accepts #RRGGBB colours', function (?string $colour, bool $valid) {
    expect(SchoolTheme::isValidHex($colour))->toBe($valid);
})->with([
    ['#0A7D3C', true],
    ['#0a7d3c', true],
    ['0a7d3c', false],
    ['#0a7', false],
    ['red', false],
    ['#0a7d3c;}', false],
    [null, false],
]);

it('returns no style for a school without a valid colour', function () {
    expect(SchoolTheme::cssVariables(new School(['primary_colour' => null])))->toBeNull()
        ->and(SchoolTheme::cssVariables(new School(['primary_colour' => 'blue'])))->toBeNull()
        ->and(SchoolTheme::cssVariables(null))->toBeNull();
});
