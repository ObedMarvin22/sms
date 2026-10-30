<?php

use App\Enums\SchoolLevel;
use App\Enums\SchoolStatus;

// Stored values are persisted in the database: renaming one would orphan existing rows.

it('keeps the stored school status values', function () {
    expect(array_column(SchoolStatus::cases(), 'value'))
        ->toBe(['trial', 'active', 'suspended']);
});

it('keeps the stored school level values, youngest first', function () {
    expect(array_column(SchoolLevel::cases(), 'value'))
        ->toBe(['creche', 'nursery', 'kg', 'primary', 'jhs', 'shs']);
});

it('labels every case', function () {
    expect(SchoolStatus::Active->label())->toBe('Active')
        ->and(SchoolLevel::Jhs->label())->toBe('JHS')
        ->and(SchoolLevel::Kg->label())->toBe('KG');
});
