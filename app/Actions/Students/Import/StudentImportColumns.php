<?php

namespace App\Actions\Students\Import;

/**
 * The columns of the import template, in order. Headings are matched case-insensitively.
 */
final class StudentImportColumns
{
    public const STUDENT = [
        'admission_number', 'first_name', 'last_name', 'other_names', 'gender', 'date_of_birth',
        'admitted_on', 'class', 'programme',
    ];

    public const GUARDIAN = ['name', 'phone', 'relationship'];

    /** How many guardians a row can carry. */
    public const GUARDIANS = 2;

    public const ALL = [
        ...self::STUDENT,
        'guardian1_name', 'guardian1_phone', 'guardian1_relationship',
        'guardian2_name', 'guardian2_phone', 'guardian2_relationship',
        // Added for the pilot (Pilot preparation step 2), at the end so older files still read.
        'residence', 'opening_balance',
    ];

    /** Largest file the dry run will read, to keep a request on shared hosting short. */
    public const MAX_ROWS = 2000;
}
