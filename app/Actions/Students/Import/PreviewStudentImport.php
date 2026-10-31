<?php

namespace App\Actions\Students\Import;

use App\Actions\Fees\FeeRules;
use App\Actions\Guardians\FindGuardianByPhone;
use App\Actions\Guardians\GuardianRules;
use App\Actions\Students\AdmitStudent;
use App\Enums\GuardianRelationship;
use App\Enums\PermissionKey;
use App\Enums\Residence;
use App\Imports\StudentRowsReader;
use App\Models\Guardian;
use App\Models\Programme;
use App\Models\Stream;
use App\Models\Student;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * The dry run: reads an import file and checks every row with the same rules as admitting a
 * student and adding a guardian, plus checks across the file. Nothing is saved.
 *
 * A guardian whose phone is already on file (or earlier in the file) is linked, not an error.
 * A student who looks already on file (same name and date of birth, or same name and class
 * when there is no date of birth), or appears twice in the file, is an error (Pilot step 2).
 */
class PreviewStudentImport
{
    /** @var array<string, int> normalised class label => stream id */
    private array $streams = [];

    /** @var array<string, int> lower-case programme name => id */
    private array $programmes = [];

    /** @var array<string, string> duplicate key => "Name (admission number)" of a student on file */
    private array $onFile = [];

    /** @var list<array{row: int, field: string, message: string}> */
    private array $errors = [];

    public function __construct(private AdmitStudent $admitStudent, private FindGuardianByPhone $findGuardian) {}

    /**
     * @param  string  $path  on the "local" (private) disk
     * @param  User|null  $user  who is importing: balances brought forward need fees.manage
     */
    public function handle(string $path, ?User $user = null): StudentImportPreview
    {
        $this->errors = [];
        $this->loadLookups();

        try {
            /** @var list<array<string, mixed>> $sheet */
            $sheet = Excel::toArray(new StudentRowsReader, $path, 'local')[0] ?? [];
        } catch (Throwable) {
            return $this->fileError('The file could not be read. Upload the .xlsx template, filled in.');
        }

        // Other heading names ("Surname", "DOB"…) count as the template's.
        $sheet = array_map(StudentImportCells::withTemplateHeadings(...), $sheet);

        // Skip blank rows but keep each row's position, so errors name the right spreadsheet row.
        $sheet = array_filter($sheet, fn (array $raw) => implode('', array_map(fn ($v) => StudentImportCells::clean($v), $raw)) !== '');

        if ($sheet === [] || ! array_key_exists('first_name', reset($sheet))) {
            return $this->fileError($sheet === [] ? 'The file has no students in it.' : 'The first sheet does not have the template\'s columns. Download the template and copy your data into it.');
        }
        if (count($sheet) > StudentImportColumns::MAX_ROWS) {
            return $this->fileError('Import at most '.StudentImportColumns::MAX_ROWS.' students at a time. Split the file.');
        }

        $canBringBalances = $user?->can(PermissionKey::FeesManage->value) ?? false;
        $offersBoarding = FeeRules::offersBoarding();

        $rows = [];
        $allCells = [];
        $admissionNumbers = []; // number => row
        $students = [];         // duplicate key => row
        $phones = [];           // +233… => [row, lower-case name]
        $existing = [];         // +233… => true, guardians already on file
        $new = [];              // +233… => true, guardians the import will create
        $boarders = $balanceCount = $owed = $credit = 0;

        // The heading is spreadsheet row 1, so the reader's first data row (index 0) is row 2.
        foreach ($sheet as $index => $raw) {
            $rowNumber = $index + 2;
            $cells = [];
            foreach (StudentImportColumns::ALL as $column) {
                $cells[$column] = StudentImportCells::clean($raw[$column] ?? null);
            }
            $allCells[] = ['row' => $rowNumber, 'cells' => $cells];

            $student = $this->studentInput($cells, $rowNumber);

            if ($student['admission_number'] !== '') {
                $key = mb_strtoupper($student['admission_number']);
                if (isset($admissionNumbers[$key])) {
                    $this->error($rowNumber, 'admission_number', "The same admission number is on row {$admissionNumbers[$key]}.");
                }
                $admissionNumbers[$key] ??= $rowNumber;
            }

            // The same child twice: in the file, or already on file.
            $duplicateKey = self::duplicateKey($student['first_name'], $student['last_name'], $student['date_of_birth'], $student['stream_id']);
            if ($duplicateKey !== null) {
                $field = $student['date_of_birth'] !== '' ? 'date_of_birth' : 'class';
                if (isset($this->onFile[$duplicateKey])) {
                    $this->error($rowNumber, 'first_name', "Looks like {$this->onFile[$duplicateKey]}, already on file. Remove the row, or correct the name or {$field} if this is a different child.");
                } elseif (isset($students[$duplicateKey])) {
                    $this->error($rowNumber, 'first_name', "The same student (name and {$field}) is on row {$students[$duplicateKey]}.");
                }
                $students[$duplicateKey] ??= $rowNumber;
            }

            // Day or boarding.
            $residence = StudentImportCells::residence($cells['residence'] ?? '');
            if ($residence === null) {
                $this->error($rowNumber, 'residence', 'Write day or boarding (or leave it blank for day).');
            } elseif ($residence === Residence::Boarding && ! $offersBoarding) {
                $this->error($rowNumber, 'residence', 'This school does not take boarders. Turn boarding on in School profile first.');
            } elseif ($residence === Residence::Boarding) {
                $boarders++;
            }

            // Balance brought forward from the school's old records.
            $balance = StudentImportCells::balance($cells['opening_balance'] ?? '');
            $balancePesewas = 0;
            if ($balance === false) {
                $this->error($rowNumber, 'opening_balance', 'Enter an amount in cedis, like 1,250.50 (owed) or -200 (in credit).');
            } elseif ($balance !== null && ! $canBringBalances) {
                $this->error($rowNumber, 'opening_balance', 'Only staff who manage fees can bring balances forward. Clear this column, or ask the bursar to import the file.');
            } elseif ($balance !== null && abs($balance->pesewas) > FeeRules::MAX_AMOUNT) {
                $this->error($rowNumber, 'opening_balance', 'That balance is too large. Check the amount.');
            } elseif ($balance !== null) {
                $balancePesewas = $balance->pesewas;
                $balanceCount++;
                $balancePesewas > 0 ? $owed += $balancePesewas : $credit -= $balancePesewas;
            }

            $guardians = $this->guardians($cells, $rowNumber, $phones, $existing, $new);

            $rows[] = [
                'row_number' => $rowNumber,
                'student' => $student,
                'guardians' => $guardians,
                'residence' => ($residence ?? Residence::Day)->value,
                'opening_balance' => $balancePesewas,
            ];
        }

        return new StudentImportPreview($rows, $this->errors, count($new), count($existing), $allCells, $boarders, $balanceCount, $owed, $credit);
    }

    /**
     * The row's guardians (up to two), checked; errors are recorded.
     *
     * @param  array<string, string>  $cells
     * @param  array<string, array{0: int, 1: string}>  $phones
     * @param  array<string, true>  $existing
     * @param  array<string, true>  $new
     * @return list<array<string, string>>
     */
    private function guardians(array $cells, int $rowNumber, array &$phones, array &$existing, array &$new): array
    {
        $guardians = [];
        $phonesInRow = [];

        for ($n = 1; $n <= StudentImportColumns::GUARDIANS; $n++) {
            $name = StudentImportCells::tidyName($cells["guardian{$n}_name"]);
            [$typedPhone, $typedAltPhone] = StudentImportCells::phones($cells["guardian{$n}_phone"]);
            $typedRelationship = $cells["guardian{$n}_relationship"];

            if ($name === '' && $typedPhone === '' && $typedRelationship === '') {
                if ($n === 1) {
                    $this->error($rowNumber, 'guardian1_name', 'Each student needs at least one guardian.');
                }

                continue;
            }

            $relationship = StudentImportCells::relationship($typedRelationship);
            if ($relationship === null) {
                $this->error($rowNumber, "guardian{$n}_relationship", 'Use one of: '.implode(', ', array_map(fn ($r) => $r->value, GuardianRelationship::cases())).'.');
            }

            $phone = Phone::normalise($typedPhone);
            if ($phone === null) {
                $this->error($rowNumber, "guardian{$n}_phone", 'Enter a Ghana phone number, e.g. 024 412 3456.');

                continue;
            }
            $altPhone = $typedAltPhone === '' ? '' : (Phone::normalise($typedAltPhone) ?? '');
            if ($typedAltPhone !== '' && $altPhone === '') {
                $this->error($rowNumber, "guardian{$n}_phone", "The second number \"{$typedAltPhone}\" is not a Ghana phone number.");
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
                        GuardianRules::details(['name' => $name, 'phone' => $phone, 'alt_phone' => $altPhone]);
                    } catch (ValidationException $e) {
                        foreach ($e->errors() as $field => $messages) {
                            $column = $field === 'alt_phone' ? 'phone' : $field;
                            $this->error($rowNumber, "guardian{$n}_{$column}", $messages[0]);
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

            $guardian = ['name' => $name, 'phone' => $phone, 'relationship' => $relationship->value ?? ''];
            if ($altPhone !== '') {
                $guardian['alt_phone'] = $altPhone;
            }
            $guardians[] = $guardian;
        }

        return $guardians;
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
            'first_name' => StudentImportCells::tidyName($cells['first_name']),
            'last_name' => StudentImportCells::tidyName($cells['last_name']),
            'other_names' => StudentImportCells::tidyName($cells['other_names']),
            'gender' => StudentImportCells::gender($cells['gender']),
            'date_of_birth' => StudentImportCells::date($cells['date_of_birth']),
            'admitted_on' => StudentImportCells::date($cells['admitted_on']),
            'stream_id' => '',
            'programme_id' => '',
        ];

        if ($cells['class'] === '') {
            $this->error($rowNumber, 'class', 'Fill in the class, e.g. JHS 1A.');
        } elseif (! isset($this->streams[StudentImportCells::key($cells['class'])])) {
            $this->error($rowNumber, 'class', "There is no class called \"{$cells['class']}\". See the Lists sheet.");
        } else {
            $input['stream_id'] = (string) $this->streams[StudentImportCells::key($cells['class'])];
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

    /**
     * How a child is recognised: first and last name with the date of birth, or with the class
     * when the date of birth is blank. Null when there is not enough to tell, including a date
     * that could not be read (that row already has its own error).
     */
    private static function duplicateKey(string $first, string $last, string $dateOfBirth, string|int|null $streamId): ?string
    {
        if ($first === '' || $last === '') {
            return null;
        }
        $name = mb_strtolower($first).'|'.mb_strtolower($last);

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateOfBirth, $m) === 1 && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return "{$name}|born:{$dateOfBirth}";
        }
        if ($dateOfBirth !== '' || (string) $streamId === '') {
            return null;
        }

        return "{$name}|stream:{$streamId}";
    }

    private function loadLookups(): void
    {
        $this->streams = [];
        $streams = Stream::query()->join('school_classes', 'school_classes.id', '=', 'streams.school_class_id')
            ->get(['streams.id', 'streams.name', 'streams.school_class_id', 'school_classes.name as class_name']);

        foreach ($streams as $stream) {
            $this->streams[StudentImportCells::key($stream->getAttribute('class_name').$stream->name)] = $stream->id;
        }
        // A class with one stream can be written without its letter ("Basic 1" for "Basic 1A").
        foreach ($streams->groupBy('school_class_id') as $classStreams) {
            if ($classStreams->count() === 1) {
                $this->streams[StudentImportCells::key((string) $classStreams->first()?->getAttribute('class_name'))] ??= (int) $classStreams->first()?->id;
            }
        }

        $this->programmes = Programme::pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower((string) $name) => (int) $id])->all();

        // Students already on file, by both keys, to spot a file imported twice.
        $this->onFile = [];
        foreach (Student::with('currentEnrolment')->get(['id', 'admission_number', 'first_name', 'last_name', 'date_of_birth']) as $student) {
            $label = "{$student->full_name} ({$student->admission_number})";
            $keys = [
                self::duplicateKey($student->first_name, $student->last_name, $student->date_of_birth?->toDateString() ?? '', null),
                self::duplicateKey($student->first_name, $student->last_name, '', $student->currentEnrolment?->stream_id),
            ];
            foreach (array_filter($keys) as $key) {
                $this->onFile[$key] ??= $label;
            }
        }
    }

    private function error(int $row, string $field, string $message): void
    {
        $this->errors[] = ['row' => $row, 'field' => $field, 'message' => $message];
    }

    private function fileError(string $message): StudentImportPreview
    {
        return new StudentImportPreview([], [['row' => 1, 'field' => 'file', 'message' => $message]], 0, 0);
    }
}
