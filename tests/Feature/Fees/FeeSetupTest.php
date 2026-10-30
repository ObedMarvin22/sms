<?php

use App\Actions\Academic\CreateAcademicYear;
use App\Actions\Classes\GenerateDefaultClasses;
use App\Actions\Fees\CopyFeeStructures;
use App\Actions\Fees\CreateFeeItem;
use App\Actions\Fees\SaveFeeStructure;
use App\Actions\Fees\SetFeeOption;
use App\Actions\Fees\SetOffersBoarding;
use App\Actions\Fees\SetResidence;
use App\Actions\Fees\UpdateFeeItem;
use App\Actions\Roles\SeedDefaultRoles;
use App\Actions\Students\AdmitStudent;
use App\Enums\AcademicPeriodType;
use App\Enums\FeeCategory;
use App\Enums\Residence;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Livewire\Fees\Items;
use App\Livewire\Fees\Structures;
use App\Livewire\School\Profile;
use App\Livewire\Students\Fees;
use App\Models\Activity;
use App\Models\FeeItem;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentFeeOption;
use App\Models\Term;
use App\Models\User;
use App\Support\Academic\DefaultCalendar;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2026-10-28 09:00:00');

    $this->school = School::factory()->active()->withLevels(SchoolLevel::Primary)->create(['subdomain' => 'greenhill']);
    app(CurrentSchool::class)->set($this->school);
    app(SeedDefaultRoles::class)->handle($this->school);
    $calendar = DefaultCalendar::for(2026, AcademicPeriodType::Term);
    app(CreateAcademicYear::class)->handle($calendar['name'], $calendar['starts_on'], $calendar['ends_on'], $calendar['terms']);
    app(GenerateDefaultClasses::class)->handle();

    [$this->term1, $this->term2] = Term::orderBy('starts_on')->take(2)->get()->all();
    $this->p1 = SchoolClass::where('name', 'Primary 1')->sole();
    $this->p2 = SchoolClass::where('name', 'Primary 2')->sole();

    $this->tuition = app(CreateFeeItem::class)->handle(['name' => 'Tuition', 'category' => 'tuition']);
    $this->pta = app(CreateFeeItem::class)->handle(['name' => 'PTA dues', 'category' => 'levy']);
    $this->bus = app(CreateFeeItem::class)->handle(['name' => 'Bus', 'category' => 'transport', 'is_optional' => true]);
});

function staffWith(RoleKey $role): User
{
    $user = User::factory()->for(app(CurrentSchool::class)->require())->withTwoFactor()->create();
    $user->assignRole($role->value);

    return $user;
}

function saveStructure(SchoolClass $class, array $amounts, ?Term $term = null, Residence $residence = Residence::Day): ?FeeStructure
{
    $byId = [];
    foreach ($amounts as $item => $amount) {
        $byId[FeeItem::where('name', $item)->sole()->id] = $amount;
    }

    return app(SaveFeeStructure::class)->handle($term ?? test()->term1, $class, $residence, $byId);
}

function feeStudent(string $first = 'Ama'): Student
{
    $stream = SchoolClass::where('name', 'Primary 1')->sole()->streams()->sole();

    return app(AdmitStudent::class)->handle(['first_name' => $first, 'last_name' => 'Mensah', 'stream_id' => $stream->id, 'admitted_on' => '2026-09-08']);
}

describe('fee items', function () {
    it('creates items and refuses duplicate names or no category', function () {
        expect($this->bus->is_optional)->toBeTrue()
            ->and($this->tuition->category)->toBe(FeeCategory::Tuition)
            ->and(fn () => app(CreateFeeItem::class)->handle(['name' => ' tuition ', 'category' => 'tuition']))->toThrow(ValidationException::class)
            ->and(fn () => app(CreateFeeItem::class)->handle(['name' => 'Books', 'category' => 'snacks']))->toThrow(ValidationException::class);
    });

    it('archives an item and logs it', function () {
        app(UpdateFeeItem::class)->handle($this->pta, ['is_active' => false]);

        $entry = Activity::where('log_name', 'fees')->where('subject_id', $this->pta->id)->where('event', 'updated')->sole();
        expect($this->pta->fresh()->is_active)->toBeFalse()
            ->and($entry->attribute_changes['old']['is_active'])->toBeTrue()
            ->and($entry->attribute_changes['attributes']['is_active'])->toBeFalse();
    });

    it('will not make an item compulsory while students take it', function () {
        app(SetFeeOption::class)->handle(feeStudent(), $this->bus, true);

        expect(fn () => app(UpdateFeeItem::class)->handle($this->bus, ['is_optional' => false]))
            ->toThrow(ValidationException::class, '1 student takes this item');
    });
});

describe('fee structures', function () {
    it('stores typed amounts as pesewas and totals them', function () {
        $structure = saveStructure($this->p1, ['Tuition' => '1,250.50', 'PTA dues' => '80', 'Bus' => '']);

        expect($structure->lines->count())->toBe(2)
            ->and(FeeStructureItem::where('fee_item_id', $this->tuition->id)->sole()->getRawOriginal('amount'))->toBe(125_050)
            ->and($structure->total()->format())->toBe('GH₵1,330.50');
    });

    it('changes, removes and logs lines; blank everywhere removes the structure', function () {
        saveStructure($this->p1, ['Tuition' => '1200', 'PTA dues' => '80']);
        saveStructure($this->p1, ['Tuition' => '1300', 'PTA dues' => '']);

        $change = Activity::where('log_name', 'fees')->where('subject_type', (new FeeStructureItem)->getMorphClass())->where('event', 'updated')->sole();
        expect(FeeStructure::sole()->total()->format())->toBe('GH₵1,300.00')
            ->and($change->attribute_changes['old']['amount'])->toBe(120_000)
            ->and($change->attribute_changes['attributes']['amount'])->toBe(130_000)
            ->and(Activity::where('log_name', 'fees')->where('event', 'deleted')->count())->toBe(1);

        expect(saveStructure($this->p1, ['Tuition' => '', 'PTA dues' => '']))->toBeNull()
            ->and(FeeStructure::count())->toBe(0)
            ->and(FeeStructureItem::count())->toBe(0);
    });

    it('logs nothing when saved without changes', function () {
        saveStructure($this->p1, ['Tuition' => '1200']);
        $before = Activity::count();

        saveStructure($this->p1, ['Tuition' => '1,200.00']);

        expect(Activity::count())->toBe($before);
    });

    it('refuses amounts that are not money', function (string $amount) {
        expect(fn () => saveStructure($this->p1, ['Tuition' => $amount]))->toThrow(ValidationException::class);
        expect(FeeStructure::count())->toBe(0);
    })->with(['abc', '0', '0.00', '12.345', '-5', '1000000.01']);

    it('keeps an archived item on its structure but will not add it to another', function () {
        saveStructure($this->p1, ['Tuition' => '1200', 'PTA dues' => '80']);
        app(UpdateFeeItem::class)->handle($this->pta, ['is_active' => false]);

        saveStructure($this->p1, ['Tuition' => '1250', 'PTA dues' => '80']);
        expect(FeeStructure::sole()->total()->format())->toBe('GH₵1,330.00');

        try {
            saveStructure($this->p2, ['Tuition' => '1200', 'PTA dues' => '80']);
            $this->fail('archived item added');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey("amounts.{$this->pta->id}");
        }
    });

    it('needs boarding turned on for boarding structures', function () {
        expect(fn () => saveStructure($this->p1, ['Tuition' => '2000'], residence: Residence::Boarding))
            ->toThrow(ValidationException::class, 'does not take boarders');

        app(SetOffersBoarding::class)->handle($this->school, true);

        expect(saveStructure($this->p1, ['Tuition' => '2000'], residence: Residence::Boarding)->residence)->toBe(Residence::Boarding);
    });

    it('copies last term\'s structures into a new term, leaving existing ones alone', function () {
        saveStructure($this->p1, ['Tuition' => '1200', 'PTA dues' => '80']);
        saveStructure($this->p2, ['Tuition' => '1300']);
        saveStructure($this->p2, ['Tuition' => '1400'], $this->term2);
        app(UpdateFeeItem::class)->handle($this->pta, ['is_active' => false]);

        expect(app(CopyFeeStructures::class)->handle($this->term1, $this->term2))->toBe(1);

        $copied = FeeStructure::with('lines')->where('term_id', $this->term2->id)->where('school_class_id', $this->p1->id)->sole();
        $kept = FeeStructure::with('lines')->where('term_id', $this->term2->id)->where('school_class_id', $this->p2->id)->sole();
        expect($copied->total()->format())->toBe('GH₵1,200.00') // archived PTA dues not copied
            ->and($kept->total()->format())->toBe('GH₵1,400.00')
            ->and(app(CopyFeeStructures::class)->handle($this->term1, $this->term2))->toBe(0)
            ->and(fn () => app(CopyFeeStructures::class)->handle($this->term2, $this->term2))->toThrow(ValidationException::class);
    });
});

describe('students', function () {
    it('lets a student opt in and out of optional items only, logging both', function () {
        $ama = feeStudent();
        app(SetFeeOption::class)->handle($ama, $this->bus, true);
        app(SetFeeOption::class)->handle($ama, $this->bus, true); // twice is fine

        expect(StudentFeeOption::where('student_id', $ama->id)->count())->toBe(1)
            ->and(fn () => app(SetFeeOption::class)->handle($ama, $this->tuition, true))->toThrow(ValidationException::class);

        app(SetFeeOption::class)->handle($ama, $this->bus, false);

        expect(StudentFeeOption::count())->toBe(0)
            ->and(Activity::where('subject_type', (new StudentFeeOption)->getMorphClass())->pluck('event')->all())->toBe(['created', 'deleted']);
    });

    it('makes a student a boarder only when the school takes boarders, and logs it', function () {
        $ama = feeStudent();

        expect(fn () => app(SetResidence::class)->handle($ama, Residence::Boarding))->toThrow(ValidationException::class);

        app(SetOffersBoarding::class)->handle($this->school, true);
        app(SetResidence::class)->handle($ama, Residence::Boarding);

        $entry = Activity::where('log_name', 'students')->where('subject_id', $ama->id)->where('event', 'updated')->sole();
        expect($ama->fresh()->residence)->toBe(Residence::Boarding)
            ->and($entry->attribute_changes['attributes']['residence'])->toBe('boarding')
            ->and(fn () => app(SetOffersBoarding::class)->handle($this->school, false))->toThrow(ValidationException::class, '1 student is a boarder');
    });

    it('starts every student as a day student', function () {
        expect(feeStudent()->fresh()->residence)->toBe(Residence::Day);
    });
});

describe('screens', function () {
    beforeEach(function () {
        $this->bursar = staffWith(RoleKey::Bursar);
        $this->head = staffWith(RoleKey::Headteacher);
        $this->teacher = staffWith(RoleKey::ClassTeacher);
        $this->onSchool($this->school);
    });

    it('lets the bursar set a class\'s fees on the grid', function () {
        Livewire::actingAs($this->bursar)->test(Structures::class)
            ->assertSet('termId', (string) $this->term1->id)
            ->call('startEdit', $this->p1->id, 'day')
            ->set("amounts.{$this->tuition->id}", '1,200')
            ->set("amounts.{$this->bus->id}", '150')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('GH₵1,200.00')
            ->assertSee('+ GH₵150.00');

        expect(FeeStructure::sole()->total()->format())->toBe('GH₵1,350.00');
    });

    it('shows a bad amount next to its item', function () {
        Livewire::actingAs($this->bursar)->test(Structures::class)
            ->call('startEdit', $this->p1->id, 'day')
            ->set("amounts.{$this->tuition->id}", 'twelve')
            ->call('save')
            ->assertHasErrors("amounts.{$this->tuition->id}");
    });

    it('copies from the previous term on the grid', function () {
        saveStructure($this->p1, ['Tuition' => '1200']);

        Livewire::withQueryParams(['term' => $this->term2->id])->actingAs($this->bursar)->test(Structures::class)
            ->assertSee('Copy from First term')
            ->call('copyFromPrevious')
            ->assertSee('GH₵1,200.00');

        expect(FeeStructure::where('term_id', $this->term2->id)->count())->toBe(1);
    });

    it('lets the headteacher look but not change fees', function () {
        saveStructure($this->p1, ['Tuition' => '1200']);

        $this->actingAs($this->head)->get('http://greenhill.localhost/fees/structures')->assertOk()->assertSee('GH₵1,200.00');
        Livewire::actingAs($this->head)->test(Structures::class)->call('startEdit', $this->p1->id, 'day')->assertForbidden();
        Livewire::actingAs($this->head)->test(Items::class)->assertDontSee('New fee item')->call('startCreate')->assertForbidden();
    });

    it('keeps staff without finance.view out', function () {
        $this->actingAs($this->teacher)->get('http://greenhill.localhost/fees/structures')->assertForbidden();
        $this->actingAs($this->teacher)->get('http://greenhill.localhost/fees/items')->assertForbidden();
    });

    it('creates and archives items on the fee items screen', function () {
        Livewire::actingAs($this->bursar)->test(Items::class)
            ->call('startCreate')
            ->set('form.name', 'Feeding')
            ->set('form.category', 'feeding')
            ->set('form.is_optional', true)
            ->call('save')
            ->assertHasNoErrors()
            ->call('startEdit', $this->pta->id)
            ->set('form.is_active', false)
            ->call('save')
            ->assertSee('Archived');

        expect(FeeItem::where('name', 'Feeding')->sole()->is_optional)->toBeTrue();
    });

    it('shows the fees panel on the student page to finance staff only', function () {
        $ama = feeStudent();

        Livewire::actingAs($this->bursar)->test(Fees::class, ['studentId' => $ama->id])
            ->assertSee('Bus')
            ->call('toggleOption', $this->bus->id)
            ->assertHasNoErrors();
        expect(StudentFeeOption::where('student_id', $ama->id)->exists())->toBeTrue();

        Livewire::actingAs($this->head)->test(Fees::class, ['studentId' => $ama->id])
            ->assertSee('Bus')
            ->call('toggleOption', $this->bus->id)->assertForbidden();

        Livewire::actingAs($this->teacher)->test(Fees::class, ['studentId' => $ama->id])->assertDontSee('Bus');
    });

    it('turns boarding on from School profile, then shows the Boarding column', function () {
        $admin = staffWith(RoleKey::Administrator);

        Livewire::actingAs($admin)->test(Profile::class)->set('offers_boarding', true)->assertHasNoErrors();
        Livewire::actingAs($this->bursar)->test(Structures::class)->assertSee('Boarding');
    });
});
