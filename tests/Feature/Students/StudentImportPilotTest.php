<?php

require_once __DIR__.'/import-helpers.php';

use App\Actions\Fees\SetOffersBoarding;
use App\Actions\Roles\SeedDefaultRoles;
use App\Actions\Students\AdmitStudent;
use App\Actions\Students\Import\ImportStudentRow;
use App\Actions\Students\Import\PreviewStudentImport;
use App\Actions\Students\Import\StartStudentImport;
use App\Actions\Students\Import\StudentImportCells;
use App\Actions\Students\Import\StudentImportColumns;
use App\Enums\LedgerEntryType;
use App\Enums\Residence;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Exports\SheetFromArray;
use App\Exports\StudentImportErrorReport;
use App\Exports\StudentImportTemplate;
use App\Livewire\Students\Import;
use App\Models\Guardian;
use App\Models\LedgerEntry;
use App\Models\School;
use App\Models\Stream;
use App\Models\Student;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Activitylog\Models\Activity;

/*
| Pilot preparation step 2: what a real school's spreadsheet contains. Stray spaces, other
| heading names, capitals, two phones in a cell, dates in words, day/boarding, old balances,
| the same child twice, and a file that needs fixing (the error report).
*/

beforeEach(function () {
    Storage::fake('local');
    $this->realQueue = Queue::getFacadeRoot();

    $this->school = School::factory()->active()->withLevels(SchoolLevel::Primary)
        ->create(['name' => 'Greenhill Academy', 'subdomain' => 'greenhill']);
    app(CurrentSchool::class)->set($this->school);
    importReady();
    app(SeedDefaultRoles::class)->handle($this->school);

    $this->bursar = User::factory()->for($this->school)->create();
    $this->bursar->assignRole(RoleKey::Bursar->value);           // fees.manage
    $this->registrar = User::factory()->for($this->school)->create();
    $this->registrar->assignRole(RoleKey::Administrator->value); // students.manage, not fees.manage
    $this->proprietor = User::factory()->for($this->school)->create();
    $this->proprietor->assignRole(RoleKey::Proprietor->value);   // both
});

function pilotPreview(array $rows, ?User $user = null)
{
    return app(PreviewStudentImport::class)->handle(importFile($rows), $user ?? test()->bursar);
}

function pilotImport(array $rows, ?User $user = null): void
{
    Queue::swap(test()->realQueue);
    app(StartStudentImport::class)->handle($user ?? test()->bursar, importFile($rows), 'pilot.xlsx');
}

/**
 * A file with the school's own headings (not the template's).
 *
 * @param  list<string>  $headings
 * @param  list<list<mixed>>  $rows
 */
function fileWithHeadings(array $headings, array $rows): string
{
    Excel::store(new SheetFromArray('Students', $headings, $rows), 'imports/own.xlsx', 'local');

    return 'imports/own.xlsx';
}

describe('messy cells', function () {
    it('cleans spaces copied from the web or WhatsApp', function () {
        $result = pilotPreview([sheetRow(['first_name' => "\u{00A0}Ama\u{200B} ", 'last_name' => "Mensah\u{00A0}\u{00A0}", 'class' => "Primary\u{00A0}1A"])]);

        expect($result->errors)->toBe([])
            ->and($result->rows[0]['student'])->toMatchArray(['first_name' => 'Ama', 'last_name' => 'Mensah']);
    });

    it('tidies names typed in capitals and leaves mixed case alone', function () {
        $result = pilotPreview([
            sheetRow(['first_name' => 'AMA SERWAA', 'last_name' => 'MENSAH', 'guardian1_name' => 'AKOSUA MENSAH']),
            sheetRow(['first_name' => 'Kwesi', 'last_name' => 'deGraft-Johnson', 'guardian1_phone' => '0201112222', 'guardian1_name' => 'Ekow deGraft']),
        ]);

        expect($result->rows[0]['student'])->toMatchArray(['first_name' => 'Ama Serwaa', 'last_name' => 'Mensah'])
            ->and($result->rows[0]['guardians'][0]['name'])->toBe('Akosua Mensah')
            ->and($result->rows[1]['student']['last_name'])->toBe('deGraft-Johnson')
            ->and($result->rows[1]['guardians'][0]['name'])->toBe('Ekow deGraft');
    });

    it('reads two phone numbers in one cell as phone and alternative phone', function (string $cell) {
        pilotImport([sheetRow(['guardian1_phone' => $cell])]);

        expect(Guardian::sole())->toMatchArray(['phone' => '+233244123456', 'alt_phone' => '+233201234567']);
    })->with(['0244123456 / 0201234567', '0244123456, 020 123 4567', '0244123456 or 0201234567', '0244123456;0201234567']);

    it('refuses a second number that is not a Ghana phone', function () {
        expect(problemKeys([sheetRow(['guardian1_phone' => '0244123456 / 12345'])]))->toBe(['2:guardian1_phone']);
    });

    it('reads dates written in words', function (string $typed) {
        expect(StudentImportCells::date($typed))->toBe('2015-09-08');
    })->with(['8 Sep 2015', '08-Sep-2015', '8 Sept 2015', '8th September 2015', 'September 8, 2015', '8 SEP 2015', '08/09/2015']);

    it('does not guess two-digit years or other words', function (string $typed) {
        expect(problemKeys([sheetRow(['date_of_birth' => $typed])]))->toBe(['2:date_of_birth']);
    })->with(['08/09/15', '8 Sep 15', 'last year']);

    it("accepts the school's own heading names", function () {
        $path = fileWithHeadings(
            ['Adm No', 'First Name', 'Surname', 'Sex', 'DOB', 'Date Admitted', 'Form', 'Parent Name', 'Parent Phone', 'Relationship', 'Day/Boarding', 'Arrears'],
            [['', 'Ama', 'Mensah', 'F', '14/03/2015', '08/09/2026', 'Primary 1A', 'Akosua Mensah', '0244123456', 'mother', 'day', '150']],
        );

        $result = app(PreviewStudentImport::class)->handle($path, $this->bursar);

        expect($result->errors)->toBe([])
            ->and($result->rows[0]['student'])->toMatchArray(['first_name' => 'Ama', 'last_name' => 'Mensah', 'gender' => 'female', 'date_of_birth' => '2015-03-14'])
            ->and($result->rows[0]['opening_balance'])->toBe(15000);
    });

    it('still refuses a file that is not a student list', function () {
        $path = fileWithHeadings(['Item', 'Price'], [['Chalk', '5']]);

        expect(app(PreviewStudentImport::class)->handle($path, $this->bursar)->errors[0]['field'])->toBe('file');
    });
});

describe('day and boarding', function () {
    it('imports boarders when the school takes them', function () {
        app(SetOffersBoarding::class)->handle($this->school, true);

        $result = pilotPreview([sheetRow(['residence' => 'Boarding']), sheetRow(['first_name' => 'Esi', 'residence' => ''])]);
        expect($result->errors)->toBe([])->and($result->boarders)->toBe(1);

        pilotImport([sheetRow(['first_name' => 'Yaw', 'residence' => 'B']), sheetRow(['first_name' => 'Adwoa'])]);
        expect(Student::where('first_name', 'Yaw')->sole()->residence)->toBe(Residence::Boarding)
            ->and(Student::where('first_name', 'Adwoa')->sole()->residence)->toBe(Residence::Day);
    });

    it('refuses boarders when the school does not take them, and unknown words', function (string $typed, string $message) {
        $result = pilotPreview([sheetRow(['residence' => $typed])]);

        expect($result->errors)->toHaveCount(1)->and($result->errors[0]['message'])->toContain($message);
    })->with([
        'no boarding' => ['boarding', 'does not take boarders'],
        'unknown' => ['weekly', 'Write day or boarding'],
    ]);
});

describe('the same child twice', function () {
    beforeEach(function () {
        $stream = Stream::whereHas('schoolClass', fn ($q) => $q->where('name', 'Primary 1'))->sole();
        $this->onFile = app(AdmitStudent::class)->handle([
            'first_name' => 'Ama', 'last_name' => 'Mensah', 'date_of_birth' => '2015-03-14', 'stream_id' => $stream->id, 'admitted_on' => '2026-09-08',
        ]);
    });

    it('refuses a student already on file by name and date of birth, naming them', function () {
        $result = pilotPreview([sheetRow(['first_name' => 'AMA', 'last_name' => 'mensah', 'date_of_birth' => '14/03/2015', 'class' => 'Primary 2A'])]);

        expect($result->errors)->toHaveCount(1)
            ->and($result->errors[0]['message'])->toContain("Ama Mensah ({$this->onFile->admission_number}), already on file");
    });

    it('refuses one by name and class when the file has no date of birth', function () {
        expect(problemKeys([sheetRow(['first_name' => 'Ama', 'last_name' => 'Mensah', 'date_of_birth' => '', 'class' => 'Primary 1A'])]))->toBe(['2:first_name']);
    });

    it('lets a child with the same name but another date of birth in', function () {
        expect(pilotPreview([sheetRow(['first_name' => 'Ama', 'last_name' => 'Mensah', 'date_of_birth' => '2016-01-01'])])->errors)->toBe([]);
    });

    it('refuses the same child twice in one file', function () {
        $result = pilotPreview([
            sheetRow(['first_name' => 'Kojo', 'date_of_birth' => '2015-05-05']),
            sheetRow(['first_name' => 'Kojo', 'date_of_birth' => '05/05/2015', 'guardian1_phone' => '0201112222', 'guardian1_name' => 'Ato Mensah']),
        ]);

        expect($result->errors)->toBe([['row' => 3, 'field' => 'first_name', 'message' => 'The same student (name and date_of_birth) is on row 2.']]);
    });

    it('turns the same file imported twice into errors, not doubled students', function () {
        $rows = [sheetRow(['first_name' => 'Kojo']), sheetRow(['first_name' => 'Efua'])];
        pilotImport($rows);

        expect(Student::count())->toBe(3)
            ->and(count(pilotPreview($rows)->errors))->toBe(2);
    });
});

describe('balances brought forward', function () {
    it('adds the old balance to each new account, logged, by the person who imported', function () {
        $result = pilotPreview([sheetRow(['opening_balance' => 'GH₵ 1,250.50']), sheetRow(['first_name' => 'Esi', 'opening_balance' => '(200)']), sheetRow(['first_name' => 'Yaw'])]);
        expect($result->errors)->toBe([])
            ->and([$result->balanceCount, $result->owedPesewas, $result->creditPesewas])->toBe([2, 125050, 20000]);

        pilotImport([sheetRow(['first_name' => 'Kojo', 'opening_balance' => '1,250.50']), sheetRow(['first_name' => 'Efua', 'opening_balance' => '-200']), sheetRow(['first_name' => 'Kwame'])]);

        $kojo = LedgerEntry::where('student_id', Student::where('first_name', 'Kojo')->sole()->id)->sole();
        expect($kojo->type)->toBe(LedgerEntryType::Adjustment)
            ->and($kojo->amount->pesewas)->toBe(125050)
            ->and($kojo->description)->toBe(ImportStudentRow::BALANCE_REASON)
            ->and($kojo->created_by)->toBe($this->bursar->id)
            ->and(LedgerEntry::balanceFor(Student::where('first_name', 'Efua')->sole()->id)->pesewas)->toBe(-20000)
            ->and(LedgerEntry::where('student_id', Student::where('first_name', 'Kwame')->sole()->id)->exists())->toBeFalse()
            ->and(Activity::where('log_name', 'payments')->where('event', 'balance_adjusted')->count())->toBe(2);
    });

    it('reads "CR" as in credit and ignores blank or zero', function () {
        expect(StudentImportCells::balance('300 CR')?->pesewas)->toBe(-30000)
            ->and(StudentImportCells::balance(''))->toBeNull()
            ->and(StudentImportCells::balance('0.00'))->toBeNull();
    });

    it('needs someone who manages fees', function () {
        $result = pilotPreview([sheetRow(['opening_balance' => '100'])], $this->registrar);

        expect($result->errors[0])->toMatchArray(['field' => 'opening_balance'])
            ->and($result->errors[0]['message'])->toContain('Only staff who manage fees');
        expect(pilotPreview([sheetRow()], $this->registrar)->errors)->toBe([]); // no balances: fine
    });

    it('refuses amounts it cannot read or that are too large', function (string $typed) {
        expect(problemKeys([sheetRow(['opening_balance' => $typed])]))->toBe(['2:opening_balance']);
    })->with(['lots', '1.234', '2000000']);
});

describe('the error report', function () {
    it('gives the rows back with an errors column and the problem cells marked', function () {
        $result = pilotPreview([
            sheetRow(['first_name' => 'Ama']),
            sheetRow(['first_name' => 'Kojo', 'class' => 'Primary 9Z', 'guardian1_phone' => '123']),
        ]);
        Excel::store(new StudentImportErrorReport($result), 'report.xlsx', 'local');

        $sheet = IOFactory::load(Storage::disk('local')->path('report.xlsx'))->getActiveSheet();
        $errorsColumn = count(StudentImportColumns::ALL) + 1;
        // Coordinates as text: PhpSpreadsheet reuses one Cell object, so cells are not kept in variables.
        $at = fn (string $column, int $row): string => Coordinate::stringFromColumnIndex(array_search($column, StudentImportColumns::ALL, true) + 1).$row;
        $fill = fn (string $coordinate): string => $sheet->getStyle($coordinate)->getFill()->getStartColor()->getARGB();

        expect($sheet->getCell([$errorsColumn, 1])->getValue())->toBe('errors')
            ->and((string) $sheet->getCell([$errorsColumn, 2])->getValue())->toBe('')
            ->and($sheet->getCell([$errorsColumn, 3])->getValue())->toContain('class: There is no class called "Primary 9Z"')
            ->and($sheet->getCell([$errorsColumn, 3])->getValue())->toContain('guardian1_phone:')
            ->and($sheet->getCell($at('class', 3))->getValue())->toBe('Primary 9Z')
            ->and($fill($at('class', 3)))->toBe(StudentImportErrorReport::PROBLEM_FILL)
            ->and($fill($at('guardian1_phone', 3)))->toBe(StudentImportErrorReport::PROBLEM_FILL)
            ->and($sheet->getStyle($at('class', 2))->getFill()->getFillType())->toBe('none')
            ->and($sheet->getStyle($at('first_name', 3))->getFill()->getFillType())->toBe('none');
    });

    it('can be uploaded again once fixed', function () {
        $result = pilotPreview([sheetRow(['class' => 'Primary 9Z'])]);
        Excel::store(new StudentImportErrorReport($result), 'imports/report.xlsx', 'local');

        // The school fixes the class cell in the report and uploads it.
        $book = IOFactory::load(Storage::disk('local')->path('imports/report.xlsx'));
        $book->getActiveSheet()->getCell([array_search('class', StudentImportColumns::ALL, true) + 1, 2])->setValue('Primary 1A');
        IOFactory::createWriter($book, 'Xlsx')->save(Storage::disk('local')->path('imports/report.xlsx'));

        expect(app(PreviewStudentImport::class)->handle('imports/report.xlsx', $this->bursar)->errors)->toBe([]);
    });

    it('downloads from the import screen', function () {
        $upload = UploadedFile::fake()->createWithContent('Greenhill students.xlsx',
            Excel::raw(new StudentImportTemplate([sheetRow(['class' => 'Primary 9Z'])]), Maatwebsite\Excel\Excel::XLSX));

        URL::defaults(['school' => 'greenhill']); // as IdentifySchool does on a real request

        Livewire::actingAs($this->proprietor)->test(Import::class)
            ->set('file', $upload)
            ->assertSee('Download the file with errors marked')
            ->call('downloadErrors')
            ->assertFileDownloaded('Greenhill students - errors.xlsx');
    });
});

describe('a 600-row file from a real school', function () {
    /**
     * 600 children in Primary 1–6 with guardians, some phones shared by siblings, typed the way
     * schools type: capitals, spaces, Excel numbers for phones, dates in words, boarders,
     * balances. $mistakes adds one known problem every 50 rows.
     *
     * @return array{rows: list<list<mixed>>, expected: list<string>}
     */
    function realSchoolFile(bool $mistakes): array
    {
        $first = ['Ama', 'Kofi', 'Esi', 'Kwame', 'Akosua', 'Yaw', 'Abena', 'Kojo', 'Efua', 'Kwesi', 'Adwoa', 'Kwaku'];
        $last = ['Mensah', 'Owusu', 'Boateng', 'Asante', 'Appiah', 'Osei', 'Agyeman', 'Darko', 'Ofori', 'Amoah'];
        $broken = [
            fn () => ['class' => 'Primary 7A'],
            fn () => ['date_of_birth' => '31/02/2015'],
            fn () => ['guardian1_phone' => '12345'],
            fn () => ['guardian1_relationship' => 'neighbour'],
            fn () => ['gender' => 'unknown'],
            fn () => ['opening_balance' => 'plenty'],
        ];
        $rows = [];
        $expected = [];

        for ($i = 0; $i < 600; $i++) {
            $phone = sprintf('024%07d', 1000000 + intdiv($i, 2)); // two siblings share each guardian
            $cells = [
                'first_name' => $i % 7 === 0 ? mb_strtoupper($first[$i % 12]) : $first[$i % 12]."\u{00A0}",
                'last_name' => $last[intdiv($i, 2) % 10],
                'gender' => $i % 2 === 0 ? 'F' : 'm',
                'date_of_birth' => $i % 3 === 0 ? date('j M Y', strtotime('2014-01-01 +'.$i.' days')) : date('d/m/Y', strtotime('2014-01-01 +'.$i.' days')),
                'admitted_on' => '08/09/2026',
                'class' => 'primary '.(($i % 6) + 1).'a',
                'guardian1_name' => 'Parent of '.$last[intdiv($i, 2) % 10].' '.intdiv($i, 2),
                'guardian1_phone' => $i % 5 === 0 ? (int) substr($phone, 1) : $phone, // some became Excel numbers
                'guardian1_relationship' => $i % 4 === 0 ? 'Father' : 'mother',
                'opening_balance' => $i % 10 === 0 ? number_format(150 + $i, 2) : '',
            ];

            if ($mistakes && $i % 50 === 25) {
                $mistake = $broken[intdiv($i, 50) % count($broken)]();
                $cells = array_merge($cells, $mistake);
                $expected[] = ($i + 2).':'.array_key_first($mistake);
            }

            $rows[] = sheetRow($cells);
        }

        return ['rows' => $rows, 'expected' => $expected];
    }

    it('lists every mistake with its row and column, and imports nothing', function () {
        ['rows' => $rows, 'expected' => $expected] = realSchoolFile(mistakes: true);

        $result = pilotPreview($rows);

        expect(array_map(fn (array $e) => $e['row'].':'.$e['field'], $result->errors))->toBe($expected)
            ->and(count($expected))->toBe(12)
            ->and(fn () => app(StartStudentImport::class)->handle($this->bursar, importFile($rows), 'real.xlsx'))->toThrow(ValidationException::class);
        expect(Student::count())->toBe(0);
    });

    it('imports all 600 once fixed, with siblings sharing guardians and balances brought forward', function () {
        ['rows' => $rows] = realSchoolFile(mistakes: false);

        expect(pilotPreview($rows)->errors)->toBe([]);
        pilotImport($rows);

        expect(Student::count())->toBe(600)
            ->and(Guardian::count())->toBe(300)
            ->and(LedgerEntry::count())->toBe(60)
            ->and(Student::whereRaw('BINARY first_name = ?', ['AMA'])->exists())->toBeFalse() // capitals tidied (MySQL compares case-insensitively)
            ->and(Student::whereRaw('BINARY first_name = ?', ['Ama'])->exists())->toBeTrue();
    });
});
