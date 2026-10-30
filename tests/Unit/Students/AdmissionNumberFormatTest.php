<?php

use App\Support\Students\AdmissionNumberFormat;

it('renders the tokens', function (string $format, string $expected) {
    expect((new AdmissionNumberFormat($format, 'GHS'))->render(2026, 7))->toBe($expected);
})->with([
    ['{PREFIX}/{YYYY}/{SEQ:4}', 'GHS/2026/0007'],
    ['{PREFIX}-{YY}-{SEQ:3}', 'GHS-26-007'],
    ['{SEQ:5}', '00007'],
    ['ADM{SEQ:1}', 'ADM7'],
]);

it('restarts numbering each year only when the year is in the number', function () {
    expect((new AdmissionNumberFormat('{PREFIX}/{YYYY}/{SEQ:4}', 'X'))->scope(2026))->toBe('2026')
        ->and((new AdmissionNumberFormat('{PREFIX}/{YY}/{SEQ:4}', 'X'))->scope(2026))->toBe('2026')
        ->and((new AdmissionNumberFormat('{PREFIX}/{SEQ:5}', 'X'))->scope(2026))->toBe('all');
});

it('explains what is wrong with a format', function (string $format, ?string $problem) {
    $actual = AdmissionNumberFormat::problem($format);

    $problem === null ? expect($actual)->toBeNull() : expect($actual)->toContain($problem);
})->with([
    ['{PREFIX}/{YYYY}/{SEQ:4}', null],
    ['{PREFIX}/{YYYY}', 'exactly once'],
    ['{SEQ:4}/{SEQ:4}', 'exactly once'],
    ['{SEQ:0}', 'between 1 and 8'],
    ['{PREFIX}/{MONTH}/{SEQ:4}', 'Only'],
    [str_repeat('A', 40).'{SEQ:4}', '40 characters'],
]);

it('makes a default prefix from the school name', function () {
    expect(AdmissionNumberFormat::defaultPrefix('Greenhill Academy'))->toBe('GA')
        ->and(AdmissionNumberFormat::defaultPrefix('Saint Mary Anglican Junior High School'))->toBe('SMAJ');
});
