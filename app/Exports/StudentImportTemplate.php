<?php

namespace App\Exports;

use App\Actions\Students\Import\StudentImportColumns;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * The import template: a "Students" sheet to fill in, and a "Lists" sheet with the values this
 * school accepts. Tests pass rows to build import files the same way a school would.
 */
class StudentImportTemplate implements Export, WithMultipleSheets
{
    /**
     * @param  list<list<mixed>>  $rows  rows for the Students sheet, in StudentImportColumns order
     * @param  array<string, list<string>>  $lists  heading => allowed values
     */
    public function __construct(private array $rows = [], private array $lists = []) {}

    /**
     * @return list<SheetFromArray>
     */
    public function sheets(): array
    {
        $headings = array_keys($this->lists);
        $height = $this->lists === [] ? 0 : max(array_map('count', $this->lists));
        $listRows = [];
        for ($i = 0; $i < $height; $i++) {
            $listRows[] = array_map(fn (array $values) => $values[$i] ?? null, array_values($this->lists));
        }

        return [
            new SheetFromArray('Students', StudentImportColumns::ALL, $this->rows),
            new SheetFromArray('Lists', $headings, $listRows),
        ];
    }
}
