<?php

namespace App\Actions\Students\Import;

use App\Actions\Guardians\FindGuardianByPhone;
use App\Actions\Guardians\GuardianRules;
use App\Actions\Students\AdmitStudent;
use App\Enums\GuardianRelationship;
use App\Imports\StudentRowsReader;
use App\Models\Guardian;
use App\Models\Programme;
use App\Models\Stream;
use App\Support\Phone;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * The dry run: reads an import file and checks every row with the same rules as admitting a
 * student and adding a guardian, plus checks across the file. Nothing is saved.
 *
 * A guardian whose phone is already on file (or earlier in the file) is linked, not an error.
 */
class PreviewStudentImport
{
    /** @var array<string, int> normalised class label => stream id */
    private array $streams = [];

    /** @var array<string, int> lower-case programme name => id */
    private array $programmes = [];

    /** @var list<array{row: int, field: string, message: string}> */
    private array $errors = [];

    public function __construct(private AdmitStudent $admitStudent, private FindGuardianByPhone $findGuardian) {}

    /**
     * @param  string  $path  on the "local" (private) disk
     */
    public function handle(string $path): StudentImportPreview
    {
        $this->errors = [];
        $this->loadLookups();

        try {
            /** @var list<array<string, mixed>> $sheet */
            $sheet = Excel::toArray(new StudentRowsReader, $path, 'local')[0] ?? [];
        } catch (Throwable) {
            return $this->fileError('The file could not be read. Upload the .xlsx template, filled in.');
        }

        // Skip blank rows but keep each row's position, so errors name the right spreadsheet row.
        $sheet = array_filter($sheet, fn (array $raw) => implode('', array_map(fn ($v) => self::cell($v), $raw)) !== '');

        if ($sheet === [] || ! array_key_exists('first_name', reset($sheet))) {
            return $this->fileError($sheet === [] ? 'The file has no students in it.' : 'The first sheet does not have the template\'s columns. Download the template and copy your data into it.');
        }
        if (count($sheet) > StudentImportColumns::MAX_ROWS) {
            return $this->fileError('Import at most '.StudentImportColumns::MAX_ROWS.' students at a time. Split the file.');
        }

        $rows = [];
        $admissionNumbers = []; // number => row
        $phones = [];           // +233… => [row, lower-case name]
        $existing = [];         // +233… => true, guardians already on file
        $new = [];              // +233… => true, guardians the import will create

        // The heading is spreadsheet row 1, so the reader's first data row (index 0) is row 2.
        foreach ($sheet as $index => $raw) {
            $rowNumber = $index + 2;
            $cells = [];
            foreach (StudentImportColumns::ALL as $column) {
                $cells[$column] = self::cell($raw[$column] ?? null);
            }

            $student = $this->studentInput($cells, $rowNumber);

            if ($student['admission_number'] !== '') {
                $key = mb_strtoupper($student['admission_number']);
                if (isset($admissionNumbers[$key])) {
                    $this->error($rowNumber, 'admission_number', "The same admission number is on row {$admissionNumbers[$key]}.");
                }
                $admissionNumbers[$key] ??= $rowNumber;
            }

            $guardians = [];
            $phonesInRow = [];
            for ($n = 1; $n <= StudentImportColumns::GUARDIANS; $n++) {
                $name = $cells["guardian{$n}_name"];
                $typedPhone = $cells["guardian{$n}_phone"];
                $typedRelationship = $cells["guardian{$n}_relationship"];

                if ($name === '' && $typedPhone === '' && $typedRelationship === '') {
                    if ($n === 1) {
                        $this->error($rowNumber, 'guardian1_name', 'Each student needs at least one guardian.');
                    }

                    continue;
                }

                $relationship = self::relationship($typedRelationship);
                if ($relationship === null) {
                    $this->error($rowNumber, "guardian{$n}_relationship", 'Use one of: '.implode(', ', array_map(fn ($r) => $r->value, GuardianRelationship::cases())).'.');
                }

                $phone = Phone::normalise($typedPhone);
                if ($phone === null) {
                    $this->error($rowNumber, "guardian{$n}_phone", 'Enter a Ghana phone number, e.g. 024 412 3456.');

                    continue;
                }

                if (isset($phonesInRow[$phone])) {
                    $this->error($rowNumber, "guardian{$n}_phone", 'Both guardians have the same phone number.');

                    continue;
                }
                $phonesInRow[$phone] = true;

                if (! isset($existing[$phone]) && ! isset($new[$phone])) {
                    if ($this->findGuardian->handle($phone) instanceof Guardian) {
                        $existing[$phone] = true;
                    } else {
                        try {
                            GuardianRules::details(['name' => $name, 'phone' => $phone]);
                        } catch (ValidationException $e) {
                            foreach ($e->errors() as $field => $messages) {
                                $this->error($rowNumber, "guardian{$n}_{$field}", $messages[0]);
                            }

                            continue;
                        }
                        $new[$phone] = true;
                    }
                }

                // A new guardian's name must be the same wherever their phone appears.
                $lowerName = mb_strtolower($name);
                if (isset($new[$phone], $phones[$phone]) && $phones[$phone][1] !== $lowerName) {
                    $this->error($rowNumber, "guardian{$n}_name", "This phone is also on row {$phones[$phone][0]} with a different name.");
                }
                $phones[$phone] ??= [$rowNumber, $lowerName];

                $guardians[] = ['name' => $name, 'phone' => $phone, 'relationship' => $relationship->value ?? ''];
            }

            $rows[] = ['row_number' => $rowNumber, 'student' => $student, 'guardians' => $guardians];
        }

        return new StudentImportPreview($rows, $this->errors, count($new), count($existing));
    }

    /**
     * AdmitStudent's input for a row, checked with AdmitStudent::prepare().
     *
     * @param  array<string, string>  $cells
     * @return array<string, string>
     */
    private function studentInput(array $cells, int $rowNumber): array
    {
        $input = [
            'admission_number' => $cells['admission_number'],
            'first_name' => $cells['first_name'],
            'last_name' => $cells['last_name'],
            'other_names' => $cells['other_names'],
            'gender' => self::gender($cells['gender']),
            'date_of_birth' => self::date($cells['date_of_birth']),
            'admitted_on' => self::date($cells['admitted_on']),
            'stream_id' => '',
            'programme_id' => '',
        ];

        if ($cells['class'] === '') {
            $this->error($rowNumber, 'class', 'Fill in the class, e.g. JHS 1A.');
        } elseif (! isset($this->streams[self::key($cells['class'])])) {
            $this->error($rowNumber, 'class', "There is no class called \"{$cells['class']}\". See the Lists sheet.");
        } else {
            $input['stream_id'] = (string) $this->streams[self::key($cells['class'])];
        }

        if ($cells['programme'] !== '') {
            if (isset($this->programmes[mb_strtolower($cells['programme'])])) {
                $input['programme_id'] = (string) $this->programmes[mb_strtolower($cells['programme'])];
            } else {
                $this->error($rowNumber, 'programme', "There is no programme called \"{$cells['programme']}\".");
            }
        }

        try {
            $this->admitStudent->prepare($input);
        } catch (ValidationException $e) {
            $columns = ['stream_id' => 'class', 'programme_id' => 'programme'];
            foreach ($e->errors() as $field => $messages) {
                $column = $columns[$field] ?? $field;
                // The class and programme lookups above already explained a missing one.
                if (! ($column === 'class' && $input['stream_id'] === '') && ! ($column === 'programme' && $cells['programme'] !== '' && $input['programme_id'] === '')) {
                    $this->error($rowNumber, $column, $messages[0]);
                }
            }
        }

        return $input;
    }

    private function loadLookups(): void
    {
        $this->streams = [];
        $streams = Stream::query()->join('school_classes', 'school_classes.id', '=', 'streams.school_class_id')
            ->get(['streams.id', 'streams.name', 'streams.school_class_id', 'school_classes.name as class_name']);

        foreach ($streams as $stream) {
            $this->streams[self::key($stream->getAttribute('class_name').$stream->name)] = $stream->id;
        }
        // A class with one stream can be written without its letter ("Basic 1" for "Basic 1A").
        foreach ($streams->groupBy('school_class_id') as $classStreams) {
            if ($classStreams->count() === 1) {
                $this->streams[self::key((string) $classStreams->first()?->getAttribute('class_name'))] ??= (int) $classStreams->first()?->id;
            }
        }

        $this->programmes = Programme::pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower((string) $name) => (int) $id])->all();
    }

    private function error(int $row, string $field, string $message): void
    {
        $this->errors[] = ['row' => $row, 'field' => $field, 'message' => $message];
    }

    private function fileError(string $message): StudentImportPreview
    {
        return new StudentImportPreview([], [['row' => 1, 'field' => 'file', 'message' => $message]], 0, 0);
    }

    /**
     * A cell as trimmed text. Whole numbers lose Excel's ".0" (phones typed as numbers).
     */
    private static function cell(mixed $value): string
    {
        if (is_float($value) && floor($value) === $value) {
            return (string) (int) $value;
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * "JHS 1A", "jhs1a" and "JHS 1 A" are the same class.
     */
    private static function key(string $label): string
    {
        return mb_strtoupper(preg_replace('/\s+/', '', $label) ?? '');
    }

    /**
     * An Excel date cell, YYYY-MM-DD, or DD/MM/YYYY (as written in Ghana), as YYYY-MM-DD.
     * Anything else is passed on unchanged for StudentRules to reject.
     */
    private static function date(string $value): string
    {
        if (preg_match('/^\d{4,5}$/', $value) === 1) {
            return ExcelDate::excelToDateTimeObject((int) $value)->format('Y-m-d');
        }
        if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', $value, $m) === 1) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        return $value;
    }

    private static function gender(string $value): string
    {
        return match (mb_strtolower($value)) {
            'f', 'female', 'girl' => 'female',
            'm', 'male', 'boy' => 'male',
            default => $value,
        };
    }

    private static function relationship(string $value): ?GuardianRelationship
    {
        $lower = mb_strtolower(trim($value));

        foreach (GuardianRelationship::cases() as $case) {
            if ($lower === $case->value || $lower === mb_strtolower($case->label()) || $lower === str_replace('_', ' ', $case->value)) {
                return $case;
            }
        }

        return null;
    }
}
