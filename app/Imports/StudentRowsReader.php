<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Reads a sheet as rows keyed by their heading (lower-case, spaces become underscores).
 * Used with Excel::toArray(); the first sheet is the Students sheet of the template.
 */
class StudentRowsReader implements Import, WithCalculatedFormulas, WithHeadingRow {}
