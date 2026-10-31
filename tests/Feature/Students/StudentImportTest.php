<?php

require_once __DIR__.'/import-helpers.php';

use App\Actions\Guardians\AddGuardian;
use App\Actions\Roles\SeedDefaultRoles;
use App\Actions\Students\AdmitStudent;
use App\Actions\Students\Import\BuildImportTemplate;
use App\Actions\Students\Import\PreviewStudentImport;
use App\Actions\Students\Import\StartStudentImport;
use App\Actions\Students\Import\StudentImportColumns;
use App\Actions\Subjects\CreateProgramme;
use App\Enums\GuardianRelationship;
use App\Enums\ImportRowStatus;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Enums\StudentImportStatus;
use App\Exports\SheetFromArray;
use App\Exports\StudentImportTemplate;
use App\Imports\StudentRowsReader;
use App\Jobs\ImportStudentsChunk;
use App\Livewire\Students\Import;
use App\Models\Enrolment;
use App\Models\Guardian;
use App\Models\GuardianStudent;
use App\Models\School;
use App\Models\Stream;
use App\Models\Student;
use App\Models\StudentImport;
use App\Models\StudentImportRow;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

beforeEach(function () {
    Storage::fake('local');
    $this->realQueue = Queue::getFacadeRoot();

    $this->school = School::factory()->active()->withLevels(SchoolLevel::Primary, SchoolLevel::Shs)
        ->create(['name' => 'Greenhill Academy', 'subdomain' => 'greenhill']);
    app(CurrentSchool::class)->set($this->school);
    importReady();
});

it('builds a template with this school\'s classes and programmes', function () {
    app(CreateProgramme::class)->handle('General Science');
    Excel::store(app(BuildImportTemplate::class)->handle(), 'template.xlsx', 'local');

    $sheets = Excel::toArray(new StudentRowsReader, 'template.xlsx', 'local');
    $headings = Excel::toArray(new class implements Maatwebsite\Excel\Concerns\Import {}, 'template.xlsx', 'local')[0][0];

    expect($headings)->toBe(StudentImportColumns::ALL)
        ->and($sheets[0])->toBe([])
        ->and(array_column($sheets[1], 'class'))->toContain('Primary 1A', 'SHS 3A')
        ->and(array_column($sheets[1], 'programme'))->toContain('General Science')
        ->and(array_column($sheets[1], 'relationship'))->toContain('mother', 'legal_guardian');
});

it('passes a clean file and counts new and existing guardians', function () {
    $sibling = app(AdmitStudent::class)->handle(['first_name' => 'Esi', 'last_name' => 'Owusu', 'stream_id' => Stream::query()->orderBy('id')->firstOrFail()->id]);
    app(AddGuardian::class)->handle($sibling, ['name' => 'Kwame Owusu', 'phone' => '0201112222', 'relationship' => 'father']);

    $result = preview([
        sheetRow(),
        sheetRow(['first_name' => 'Ama', 'gender' => 'female']), // same mother: one new guardian
        sheetRow(['first_name' => 'Yaw', 'last_name' => 'Owusu', 'guardian1_name' => 'Kwame Owusu', 'guardian1_phone' => '+233 20 111 2222', 'guardian1_relationship' => 'father']),
    ]);

    expect($result->errors)->toBe([])
        ->and($result->isClean())->toBeTrue()
        ->and($result->rows)->toHaveCount(3)
        ->and($result->newGuardians)->toBe(1)
        ->and($result->linkedGuardians)->toBe(1)
        ->and($result->rows[0]['guardians'][0])->toBe(['name' => 'Akosua Mensah', 'phone' => '+233244123456', 'relationship' => 'mother']);
});

it('reports each row problem with its row and column', function (array $cells, string $field) {
    expect(problemKeys([sheetRow(), sheetRow($cells)]))->toBe(["3:{$field}"]);
})->with([
    'no first name' => [['first_name' => ''], 'first_name'],
    'no class' => [['class' => ''], 'class'],
    'unknown class' => [['class' => 'Primary 9Z'], 'class'],
    'bad gender' => [['gender' => 'x'], 'gender'],
    'bad date' => [['date_of_birth' => '31/02/2015'], 'date_of_birth'],
    'future birth' => [['date_of_birth' => '2099-01-01'], 'date_of_birth'],
    'admitted outside the year' => [['admitted_on' => '2025-01-10'], 'admitted_on'],
    'SHS without programme' => [['class' => 'SHS 1A'], 'programme'],
    'unknown programme' => [['class' => 'SHS 1A', 'programme' => 'Astronomy'], 'programme'],
    'no guardian' => [['guardian1_name' => '', 'guardian1_phone' => '', 'guardian1_relationship' => ''], 'guardian1_name'],
    'guardian without name' => [['guardian1_name' => '', 'guardian1_phone' => '0559998888'], 'guardian1_name'],
    'bad phone' => [['guardian1_phone' => '12345'], 'guardian1_phone'],
    'bad relationship' => [['guardian1_relationship' => 'cousin'], 'guardian1_relationship'],
    'same phone twice in a row' => [['guardian2_name' => 'Kojo Mensah', 'guardian2_phone' => '024 412 3456', 'guardian2_relationship' => 'father'], 'guardian2_phone'],
]);

it('refuses an admission number already used in the school', function () {
    app(AdmitStudent::class)->handle(['first_name' => 'Esi', 'last_name' => 'Owusu', 'admission_number' => 'GA/001', 'stream_id' => Stream::query()->orderBy('id')->firstOrFail()->id]);

    expect(problemKeys([sheetRow(['admission_number' => 'GA/001'])]))->toBe(['2:admission_number']);
});

it('checks across the whole file', function () {
    expect(problemKeys([
        sheetRow(['admission_number' => 'GA/100']),
        sheetRow(['admission_number' => 'ga/100', 'guardian1_phone' => '0201112222', 'guardian1_name' => 'Kwame Boateng']),
        sheetRow(['guardian1_name' => 'Someone Else']), // the mother's phone with another name
    ]))->toBe(['3:admission_number', '4:guardian1_name']);
});

it('accepts the ways people fill in spreadsheets', function () {
    $result = preview([
        sheetRow([
            'date_of_birth' => ExcelDate::PHPToExcel('2015-03-14'), // a real Excel date cell
            'admitted_on' => '08/09/2026',                        // DD/MM/YYYY
            'guardian1_phone' => 244123456,                       // a number: Excel dropped the 0
            'class' => 'primary 1a', 'gender' => 'F', 'guardian1_relationship' => 'Aunt or uncle',
        ]),
        sheetRow(['class' => '', 'first_name' => '', 'last_name' => '', 'gender' => '', 'date_of_birth' => '', 'admitted_on' => '', 'guardian1_name' => '', 'guardian1_phone' => '', 'guardian1_relationship' => '']), // blank row
        sheetRow(['first_name' => 'Ama', 'class' => 'SHS 1A', 'programme' => 'general science']),
    ]);

    expect($result->errors)->toBe([['row' => 4, 'field' => 'programme', 'message' => 'There is no programme called "general science".']]);

    app(CreateProgramme::class)->handle('General Science');
    $result = preview([sheetRow(['date_of_birth' => ExcelDate::PHPToExcel('2015-03-14'), 'admitted_on' => '08/09/2026', 'guardian1_phone' => 244123456, 'class' => 'primary 1a', 'gender' => 'F', 'guardian1_relationship' => 'Aunt or uncle'])]);

    expect($result->errors)->toBe([])
        ->and($result->rows[0]['student'])->toMatchArray(['gender' => 'female', 'date_of_birth' => '2015-03-14', 'admitted_on' => '2026-09-08'])
        ->and($result->rows[0]['guardians'][0])->toMatchArray(['phone' => '+233244123456', 'relationship' => GuardianRelationship::AuntUncle->value]);
});

it('refuses files that are not a filled-in template', function (Closure $write, string $message) {
    $result = app(PreviewStudentImport::class)->handle($write());

    expect($result->isClean())->toBeFalse()
        ->and($result->errors[0]['field'])->toBe('file')
        ->and($result->errors[0]['message'])->toContain($message);
})->with([
    'empty template' => [fn () => importFile([]), 'no students'],
    'other columns' => [function () {
        Excel::store(new SheetFromArray('Sheet1', ['name', 'age'], [['Kofi', 9]]), 'imports/other.xlsx', 'local');

        return 'imports/other.xlsx';
    }, 'template\'s columns'],
    'not a spreadsheet' => [function () {
        Storage::disk('local')->put('imports/fake.xlsx', 'this is not a spreadsheet');

        return 'imports/fake.xlsx';
    }, 'could not be read'],
]);

it('imports nothing from a file with errors', function () {
    $path = importFile([sheetRow(), sheetRow(['class' => 'Nowhere'])]);

    expect(fn () => app(StartStudentImport::class)->handle(importer(), $path, 'students.xlsx'))
        ->toThrow(ValidationException::class, 'nothing was imported');

    expect(Student::count())->toBe(0)->and(StudentImport::count())->toBe(0);
});

it('imports 600 students with their guardians, 100 rows per job', function () {
    $rows = [];
    for ($i = 1; $i <= 600; $i++) {
        $family = intdiv($i + 1, 2); // two children per guardian
        $rows[] = sheetRow([
            'first_name' => "Child{$i}", 'class' => $i % 2 ? 'Primary 1A' : 'Primary 2A',
            'guardian1_name' => "Parent {$family}", 'guardian1_phone' => '024'.sprintf('%07d', $family),
        ]);
    }
    $path = importFile($rows);

    $import = app(StartStudentImport::class)->handle(importer(), $path, 'whole-school.xlsx');

    $import->refresh();
    $year = now()->year;
    expect($import->status)->toBe(StudentImportStatus::Completed)
        ->and($import->counts())->toBe(['pending' => 0, 'imported' => 600, 'failed' => 0])
        ->and(Student::count())->toBe(600)
        ->and(Enrolment::where('is_current', true)->count())->toBe(600)
        ->and(Guardian::count())->toBe(300)
        ->and(GuardianStudent::count())->toBe(600)
        ->and(GuardianStudent::where('is_primary', true)->count())->toBe(600)
        ->and(Student::orderBy('id')->pluck('admission_number')->last())->toBe("GA/{$year}/0600")
        ->and(Storage::disk('local')->exists($path))->toBeFalse();
});

it('queues the rows in chunks of 100 for this school', function () {
    Queue::fake();
    $rows = array_map(fn (int $i) => sheetRow(['first_name' => "Child{$i}"]), range(1, 250));

    $import = app(StartStudentImport::class)->handle(importer(), importFile($rows), 'students.xlsx');

    Queue::assertPushed(ImportStudentsChunk::class, 3);
    Queue::assertPushed(fn (ImportStudentsChunk $job) => $job->schoolId() === $this->school->id && $job->importId === $import->id && count($job->rowIds) === 100);
    expect(StudentImportRow::where('student_import_id', $import->id)->count())->toBe(250)
        ->and(Student::count())->toBe(0);
});

it('never imports a row twice when a job is retried', function () {
    Queue::fake();
    $import = app(StartStudentImport::class)->handle(importer(), importFile([sheetRow(), sheetRow(['first_name' => 'Ama'])]), 'students.xlsx');
    $job = new ImportStudentsChunk($this->school->id, $import->id, StudentImportRow::pluck('id')->all());

    runJob($job);
    runJob($job);

    expect(Student::count())->toBe(2)->and(Guardian::count())->toBe(1)->and(GuardianStudent::count())->toBe(2);
});

it('marks a row failed when something changed after the preview, and imports the rest', function () {
    Queue::fake();
    $import = app(StartStudentImport::class)->handle(importer(), importFile([
        sheetRow(['admission_number' => 'GA/050']),
        sheetRow(['first_name' => 'Ama']),
    ]), 'students.xlsx');

    // Someone admits GA/050 by hand while the import waits in the queue.
    app(AdmitStudent::class)->handle(['first_name' => 'Esi', 'last_name' => 'Owusu', 'admission_number' => 'GA/050', 'stream_id' => Stream::query()->orderBy('id')->firstOrFail()->id]);

    runJob(new ImportStudentsChunk($this->school->id, $import->id, StudentImportRow::pluck('id')->all()));

    $failed = StudentImportRow::where('row_number', 2)->sole();
    expect($failed->status)->toBe(ImportRowStatus::Failed)
        ->and($failed->error)->toContain('admission_number: Another student has this admission number')
        ->and(StudentImportRow::where('row_number', 3)->sole()->status)->toBe(ImportRowStatus::Imported)
        ->and($import->fresh()->status)->toBe(StudentImportStatus::Completed)
        ->and(Student::count())->toBe(2)
        ->and(Guardian::count())->toBe(1); // the failed row's guardian work was rolled back, Ama's was kept
});

it('runs the job as its own school, even from a worker with no school', function () {
    Queue::fake();
    $import = app(StartStudentImport::class)->handle(importer(), importFile([sheetRow()]), 'students.xlsx');
    $rowIds = StudentImportRow::pluck('id')->all();

    $other = School::factory()->active()->withLevels(SchoolLevel::Primary)->create(['subdomain' => 'hillview']);
    app(CurrentSchool::class)->clear();

    // A job dispatched for another school cannot see these rows, so it does nothing.
    runJob(new ImportStudentsChunk($other->id, $import->id, $rowIds));
    expect(app(CurrentSchool::class)->has())->toBeFalse();

    runJob(new ImportStudentsChunk($this->school->id, $import->id, $rowIds));
    expect(app(CurrentSchool::class)->has())->toBeFalse();

    app(CurrentSchool::class)->set($this->school);
    expect(Student::count())->toBe(1)->and(Student::sole()->school_id)->toBe($this->school->id);
    app(CurrentSchool::class)->runFor($other, fn () => expect(Student::count())->toBe(0));
});

describe('screen', function () {
    beforeEach(function () {
        app(SeedDefaultRoles::class)->handle($this->school);

        $this->admin = User::factory()->for($this->school)->withTwoFactor()->create();
        $this->admin->assignRole(RoleKey::Administrator->value);
        $this->teacher = User::factory()->for($this->school)->create();
        $this->teacher->assignRole(RoleKey::ClassTeacher->value);
        $this->onSchool($this->school);
    });

    function upload(array $rows): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('students.xlsx', Excel::raw(new StudentImportTemplate($rows), Maatwebsite\Excel\Excel::XLSX));
    }

    it('downloads the template', function () {
        $this->actingAs($this->admin)->get('http://greenhill.localhost/students/import/template')
            ->assertOk()->assertDownload('students-import-greenhill.xlsx');
    });

    it('shows the problems in an uploaded file and imports nothing', function () {
        Livewire::actingAs($this->admin)->test(Import::class)
            ->set('file', upload([sheetRow(), sheetRow(['class' => 'Primary 9Z'])]))
            ->assertSee('1 problem to fix')
            ->assertSee('There is no class called')
            ->assertDontSee('Import 2 students');

        expect(Student::count())->toBe(0);
    });

    it('previews a clean file, then imports it', function () {
        Livewire::actingAs($this->admin)->test(Import::class)
            ->set('file', upload([sheetRow(), sheetRow(['first_name' => 'Ama'])]))
            ->assertSee('Ready to import 2 students')
            ->call('startImport')
            ->assertHasNoErrors()
            ->assertSee('Finished')
            ->assertSee('2 imported, 0 failed');

        expect(Student::count())->toBe(2)->and(StudentImport::sole()->user_id)->toBe($this->admin->id);
    });

    it('refuses other file types', function () {
        Livewire::actingAs($this->admin)->test(Import::class)
            ->set('file', UploadedFile::fake()->createWithContent('students.csv', "first_name\nKofi"))
            ->assertHasErrors('file');
    });

    it('is only for staff who manage students', function () {
        $this->actingAs($this->teacher)->get('http://greenhill.localhost/students/import')->assertForbidden();
        $this->actingAs($this->teacher)->get('http://greenhill.localhost/students/import/template')->assertForbidden();
        $this->actingAs($this->admin)->get('http://greenhill.localhost/students/import')->assertOk()->assertSee('Download template');
    });
});
