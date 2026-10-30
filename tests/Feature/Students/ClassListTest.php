<?php

use App\Actions\Academic\CreateAcademicYear;
use App\Actions\Classes\GenerateDefaultClasses;
use App\Actions\Guardians\AddGuardian;
use App\Actions\Roles\SeedDefaultRoles;
use App\Actions\Students\AdmitStudent;
use App\Actions\Students\BuildClassList;
use App\Actions\Students\MoveStudent;
use App\Actions\Students\SearchStudents;
use App\Actions\Students\WithdrawStudent;
use App\Enums\AcademicPeriodType;
use App\Enums\Gender;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Enums\StudentStatus;
use App\Livewire\Students\ClassList;
use App\Livewire\Students\Index;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Stream;
use App\Models\Student;
use App\Models\User;
use App\Support\Academic\DefaultCalendar;
use App\Support\Tenancy\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Livewire\Livewire;

require_once __DIR__.'/../Attendance/helpers.php';

beforeEach(function () {
    $this->travelTo('2026-10-28 08:00:00');

    $this->school = School::factory()->active()->withLevels(SchoolLevel::Primary)->create(['name' => 'Greenhill Academy', 'subdomain' => 'greenhill']);
    app(CurrentSchool::class)->set($this->school);
    app(SeedDefaultRoles::class)->handle($this->school);
    $calendar = DefaultCalendar::for(2026, AcademicPeriodType::Term);
    app(CreateAcademicYear::class)->handle($calendar['name'], $calendar['starts_on'], $calendar['ends_on'], $calendar['terms']);
    app(GenerateDefaultClasses::class)->handle();

    $this->p1 = SchoolClass::where('name', 'Primary 1')->sole()->streams()->sole();
    $this->p2 = SchoolClass::where('name', 'Primary 2')->sole()->streams()->sole();
});

/**
 * @param  array<string, mixed>  $input
 */
function listPupil(string $first, string $last, Stream $stream, array $input = []): Student
{
    return app(AdmitStudent::class)->handle(array_merge(
        ['first_name' => $first, 'last_name' => $last, 'stream_id' => $stream->id, 'admitted_on' => '2026-09-08'],
        $input,
    ));
}

/**
 * @param  array<string, mixed>  $filters
 * @return list<string>
 */
function searchNames(array $filters): array
{
    return app(SearchStudents::class)->handle($filters)->get()->map(fn (Student $s) => $s->first_name)->values()->all();
}

describe('class list', function () {
    it('lists the stream\'s students today with their primary contact and gender totals', function () {
        $ama = listPupil('Ama', 'Boateng', $this->p1, ['gender' => 'female', 'date_of_birth' => '2019-03-02']);
        listPupil('Kofi', 'Asante', $this->p1, ['gender' => 'male']);
        listPupil('Yaw', 'Darko', $this->p2, ['gender' => 'male']);
        app(AddGuardian::class)->handle($ama, ['name' => 'Akosua Boateng', 'phone' => '0244000001', 'relationship' => 'mother']);
        app(AddGuardian::class)->handle($ama, ['name' => 'Kwame Boateng', 'phone' => '0244000002', 'relationship' => 'father']);

        $list = app(BuildClassList::class)->handle($this->p1);

        expect(array_column($list['rows'], 'name'))->toBe(['Kofi Asante', 'Ama Boateng'])
            ->and($list['rows'][1]['guardian'])->toBe('Akosua Boateng')
            ->and($list['rows'][1]['guardian_phone'])->toBe('+233244000001')
            ->and($list['rows'][1]['gender'])->toBe(Gender::Female)
            ->and($list['rows'][0]['guardian'])->toBeNull()
            ->and([$list['total'], $list['boys'], $list['girls']])->toBe([2, 1, 1]);
    });

    it('agrees with the register on who has moved or left', function () {
        $moved = listPupil('Esi', 'Owusu', $this->p1);
        $left = listPupil('Nana', 'Ofori', $this->p1);
        listPupil('Abena', 'Mensah', $this->p1);
        app(MoveStudent::class)->handle($moved, $this->p2, on: '2026-10-26');
        app(WithdrawStudent::class)->handle($left, StudentStatus::Withdrawn, '2026-10-20');

        expect(array_column(app(BuildClassList::class)->handle($this->p1)['rows'], 'name'))->toBe(['Abena Mensah'])
            ->and(array_column(app(BuildClassList::class)->handle($this->p2)['rows'], 'name'))->toBe(['Esi Owusu'])
            ->and(array_column(app(BuildClassList::class)->handle($this->p1, CarbonImmutable::parse('2026-10-19'))['rows'], 'name'))
            ->toBe(['Abena Mensah', 'Nana Ofori', 'Esi Owusu']);
    });
});

describe('student search', function () {
    beforeEach(function () {
        $this->ama = listPupil('Ama', 'Mensah', $this->p1, ['gender' => 'female']);
        $this->kofi = listPupil('Kofi', 'Mensah', $this->p2, ['gender' => 'male']);
        $this->esi = listPupil('Esi', 'Amankwah', $this->p1, ['gender' => 'female']);
    });

    it('matches every typed word against the names', function () {
        expect(searchNames(['search' => 'ama mensah']))->toBe(['Ama'])
            ->and(searchNames(['search' => 'Mensah']))->toEqualCanonicalizing(['Ama', 'Kofi'])
            ->and(searchNames(['search' => 'ama']))->toEqualCanonicalizing(['Ama', 'Esi']) // Amankwah
            ->and(searchNames(['search' => $this->kofi->admission_number]))->toBe(['Kofi']);
    });

    it('finds a student by any guardian\'s phone, typed in any format', function () {
        app(AddGuardian::class)->handle($this->kofi, ['name' => 'Efua Mensah', 'phone' => '0244123456', 'relationship' => 'mother']);

        expect(searchNames(['search' => '0244 123 456']))->toBe(['Kofi'])
            ->and(searchNames(['search' => '+233244123456']))->toBe(['Kofi'])
            ->and(searchNames(['search' => '123456']))->toBe(['Kofi'])
            ->and(searchNames(['search' => '12']))->toBe([]); // too short to mean a phone
    });

    it('filters by stream, status and gender', function () {
        app(WithdrawStudent::class)->handle($this->esi, StudentStatus::Withdrawn, '2026-10-20');

        expect(searchNames(['stream_id' => $this->p2->id]))->toBe(['Kofi'])
            ->and(searchNames(['gender' => 'female']))->toEqualCanonicalizing(['Ama', 'Esi'])
            ->and(searchNames(['status' => 'withdrawn']))->toBe(['Esi'])
            ->and(searchNames(['status' => 'active', 'gender' => 'female']))->toBe(['Ama']);
    });

    it('shows active students by default on the screen, and everyone with All', function () {
        app(WithdrawStudent::class)->handle($this->esi, StudentStatus::Withdrawn, '2026-10-20');
        $admin = User::factory()->for($this->school)->withTwoFactor()->create();
        $admin->assignRole(RoleKey::Administrator->value);
        $this->onSchool($this->school);

        Livewire::actingAs($admin)->test(Index::class)
            ->assertSee('Ama Mensah')->assertDontSee('Esi Amankwah')
            ->set('statusFilter', '')
            ->assertSee('Esi Amankwah')
            ->set('search', 'kofi')
            ->assertSee('Kofi Mensah')->assertDontSee('Ama Mensah');
    });
});

describe('screens and PDF', function () {
    beforeEach(function () {
        listPupil('Ama', 'Mensah', $this->p1, ['gender' => 'female']);
        listPupil('Yaw', 'Darko', $this->p2, ['gender' => 'male']);
        $this->onSchool($this->school);
    });

    it('opens a class teacher on their own stream', function () {
        $teacher = classTeacherOf($this->p2);

        Livewire::actingAs($teacher)->test(ClassList::class)
            ->assertSet('streamId', (string) $this->p2->id)
            ->assertSee('Yaw Darko')->assertDontSee('Ama Mensah')
            ->assertSee('1 students: 1 boys, 0 girls')
            ->set('streamId', (string) $this->p1->id)
            ->assertSee('Ama Mensah');
    });

    it('downloads the class list as a PDF', function () {
        $teacher = classTeacherOf($this->p1);

        $response = $this->actingAs($teacher)->get(route('class-lists.pdf', $this->p1))->assertOk();

        expect($response->headers->get('Content-Type'))->toBe('application/pdf')
            ->and($response->headers->get('Content-Disposition'))->toContain(Str::slug($this->p1->full_name).'-class-list-2026-10-28.pdf')
            ->and(substr((string) $response->getContent(), 0, 5))->toBe('%PDF-')
            ->and(strlen((string) $response->getContent()))->toBeLessThan(200_000); // fonts subset, not embedded whole
    });

    it('never prints another school\'s stream', function () {
        $other = School::factory()->active()->create(['subdomain' => 'hillview']);
        $theirs = app(CurrentSchool::class)->runFor($other, fn () => Stream::factory()->for(SchoolClass::factory()->for($other))->create());
        $teacher = classTeacherOf($this->p1);

        $this->actingAs($teacher)->get(route('class-lists.pdf', $theirs->id))->assertNotFound();
    });

    it('refuses staff without students.view', function () {
        $gate = User::factory()->for($this->school)->create();
        $gate->assignRole(RoleKey::GateStaff->value);

        $this->actingAs($gate)->get(route('class-lists.index'))->assertForbidden();
        $this->actingAs($gate)->get(route('class-lists.pdf', $this->p1))->assertForbidden();
    });
});
