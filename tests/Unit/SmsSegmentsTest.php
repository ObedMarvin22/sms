<?php

use App\Support\Sms\SmsSegments;

it('counts SMS units', function (string $message, int $units) {
    expect(SmsSegments::count($message))->toBe($units);
})->with([
    'short' => ['Ama was marked absent today.', 1],
    'empty' => ['', 0],
    '160 GSM characters' => [str_repeat('a', 160), 1],
    '161 GSM characters' => [str_repeat('a', 161), 2],
    '306 GSM characters' => [str_repeat('a', 306), 2],
    '307 GSM characters' => [str_repeat('a', 307), 3],
    'extension characters count twice' => [str_repeat('{', 80), 1],
    'one more extension character' => [str_repeat('{', 81), 2],
    'cedi sign makes it Unicode (70)' => ['GH₵'.str_repeat('a', 67), 1],
    'Unicode over 70' => ['GH₵'.str_repeat('a', 68), 2],
    'GHS stays GSM' => ['GHS500.00 for Ama (receipt GA/R/2026/00001). Balance: GHS600.00.', 1],
]);
