<?php

namespace App\Exports;

use App\Actions\Students\Import\StudentImportColumns;
use App\Actions\Students\Import\StudentImportPreview;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The school's own rows, as read, with an "errors" column and each problem cell highlighted
 * (Pilot preparation step 2). The columns are the template's, so the school fixes the cells and
 * uploads this same file again; the extra "errors" column is ignored by the import.
 */
class StudentImportErrorReport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public const PROBLEM_FILL = 'FFFDE2E2';

    public function __construct(private StudentImportPreview $preview) {}

    /**
     * @return list<list<string>>
     */
    public function array(): array
    {
        $messages = $this->messagesByRow();

        return array_map(fn (array $row) => [
            ...array_map(fn (string $column) => $row['cells'][$column] ?? '', StudentImportColumns::ALL),
            implode(' ', $messages[$row['row']] ?? []),
        ], $this->preview->cells);
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [...StudentImportColumns::ALL, 'errors'];
    }

    public function title(): string
    {
        return 'Students';
    }

    /**
     * @return array<int|string, array<string, mixed>> cell, column or row => style
     */
    public function styles(Worksheet $sheet): array
    {
        $columns = array_flip(StudentImportColumns::ALL);
        $reportRow = []; // spreadsheet row in the school's file => row in this report
        foreach ($this->preview->cells as $i => $row) {
            $reportRow[$row['row']] = $i + 2;
        }

        $styles = [1 => ['font' => ['bold' => true]]];
        $fill = ['fill' => ['fillType' => 'solid', 'startColor' => ['argb' => self::PROBLEM_FILL]]];
        foreach ($this->preview->errors as $error) {
            if (! isset($reportRow[$error['row']], $columns[$error['field']])) {
                continue;
            }
            $styles[Coordinate::stringFromColumnIndex($columns[$error['field']] + 1).$reportRow[$error['row']]] = $fill;
        }

        $errorsColumn = Coordinate::stringFromColumnIndex(count(StudentImportColumns::ALL) + 1);
        $styles[$errorsColumn] = ['font' => ['color' => ['argb' => 'FFB91C1C']]];

        return $styles;
    }

    /**
     * @return array<int, list<string>> row => "column: message" lines
     */
    private function messagesByRow(): array
    {
        $messages = [];
        foreach ($this->preview->errors as $error) {
            $messages[$error['row']][] = "{$error['field']}: {$error['message']}";
        }

        return $messages;
    }
}
