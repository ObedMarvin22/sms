<?php

/*
| Helpers shared by the student import test files (loaded with require_once, so declared once).
*/

use App\Actions\Academic\CreateAcademicYear;
use App\Actions\Classes\GenerateDefaultClasses;
use App\Actions\Students\Import\PreviewStudentImport;
use App\Actions\Students\Import\StudentImportColumns;
use App\Enums\AcademicPeriodType;
use App\Exports\StudentImportTemplate;
use App\Jobs\ImportStudentsChunk;
use App\Models\User;
use App\Support\Academic\DefaultCalendar;
use App\Support\Tenancy\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Maatwebsite\Excel\Facades\Excel;

function importReady(): void
{
    $calendar = DefaultCalendar::for(2026, AcademicPeriodType::Term);
    app(CreateAcademicYear::class)->handle($calendar['name'], $calendar['starts_on'], $calendar['ends_on'], $calendar['terms']);
    app(GenerateDefaultClasses::class)->handle();
}

/**
 * One spreadsheet row in template column order; override cells by heading.
 *
 * @return list<mixed>
 */
function sheetRow(array $cells = []): array
{
    // Each row is a different child unless a test says otherwise (Pilot step 2 refuses the same
    // name and date of birth twice), so the default date of birth moves on a day per row.
    static $n = 0;
    $n++;

    $row = array_merge([
        'admission_number' => '', 'first_name' => 'Kofi', 'last_name' => 'Mensah', 'other_names' => '',
        'gender' => 'male', 'date_of_birth' => CarbonImmutable::parse('2014-01-01')->addDays($n % 2000)->toDateString(), 'admitted_on' => '2026-09-08', 'class' => 'Primary 1A',
        'programme' => '', 'guardian1_name' => 'Akosua Mensah', 'guardian1_phone' => '0244123456',
        'guardian1_relationship' => 'mother', 'guardian2_name' => '', 'guardian2_phone' => '', 'guardian2_relationship' => '',
        'residence' => '', 'opening_balance' => '',
    ], $cells);

    return array_map(fn (string $column) => $row[$column], StudentImportColumns::ALL);
}

/**
 * Writes an import file to the private disk, the way a school would fill in the template.
 */
function importFile(array $rows): string
{
    Excel::store(new StudentImportTemplate($rows), 'imports/test.xlsx', 'local');

    return 'imports/test.xlsx';
}

function preview(array $rows)
{
    return app(PreviewStudentImport::class)->handle(importFile($rows));
}

/**
 * @return list<string> "row:field" for each error
 */
function problemKeys(array $rows): array
{
    return array_map(fn (array $e) => $e['row'].':'.$e['field'], preview($rows)->errors);
}

/**
 * Runs a job on the real sync queue (with its middleware), even while Queue::fake() is on.
 */
function runJob(ImportStudentsChunk $job): void
{
    Queue::swap(test()->realQueue);
    dispatch_sync($job);
}

function importer(): User
{
    return User::factory()->for(app(CurrentSchool::class)->require())->create();
}
