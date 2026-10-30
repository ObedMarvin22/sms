<?php

namespace Database\Seeders;

use App\Actions\Guardians\AddGuardian;
use App\Actions\Guardians\LinkGuardian;
use App\Actions\Students\AdmitStudent;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\Programme;
use App\Models\Stream;
use App\Models\Student;
use App\Models\Term;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Database\Seeder;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Local demo students for the current school (run by DemoSchoolsSeeder inside each school):
 * config('app.demo_students_per_school') students (600 locally) spread over the streams, in
 * families of one to three children who share their guardians.
 *
 * Everything goes through the Actions, so demo data obeys the real rules. A fixed random seed
 * per school gives the same names and numbers on every migrate:fresh --seed.
 */
class DemoStudentsSeeder extends Seeder
{
    private const MALE = ['Kofi', 'Kwame', 'Kwabena', 'Kwaku', 'Yaw', 'Kwasi', 'Kojo', 'Emmanuel', 'Daniel', 'Samuel', 'Michael',
        'Joseph', 'Isaac', 'Prince', 'Richard', 'Godfred', 'Benjamin', 'Elikem', 'Selorm', 'Nii', 'Ebo', 'Fiifi', 'Kelvin', 'David'];

    private const FEMALE = ['Ama', 'Akosua', 'Abena', 'Akua', 'Yaa', 'Efua', 'Adwoa', 'Esi', 'Grace', 'Abigail', 'Mercy',
        'Priscilla', 'Gifty', 'Esther', 'Deborah', 'Comfort', 'Joyce', 'Linda', 'Naa', 'Dzifa', 'Elorm', 'Sedinam', 'Nhyira', 'Maame'];

    private const DAY_NAMES = ['male' => ['Kwadwo', 'Kwabena', 'Kwaku', 'Yaw', 'Kofi', 'Kwame', 'Kwasi'], 'female' => ['Adwoa', 'Abena', 'Akua', 'Yaa', 'Afua', 'Ama', 'Akosua']];

    private const SURNAMES = ['Mensah', 'Asante', 'Owusu', 'Boateng', 'Osei', 'Agyeman', 'Appiah', 'Ofori', 'Darko', 'Amoah',
        'Annan', 'Quaye', 'Tetteh', 'Addo', 'Adjei', 'Nkrumah', 'Frimpong', 'Gyamfi', 'Sarpong', 'Opoku', 'Ansah', 'Bonsu',
        'Acheampong', 'Kumi', 'Larbi', 'Lamptey', 'Quartey', 'Agbeko', 'Dzeble', 'Mahama', 'Abdulai', 'Fuseini', 'Amponsah',
        'Danso', 'Antwi', 'Asamoah', 'Badu', 'Kyei', 'Nyarko', 'Wiredu'];

    /** Mobile prefixes without the leading 0 (MTN, Telecel, AirtelTigo). */
    private const PREFIXES = ['24', '54', '55', '59', '20', '50', '27', '57', '26'];

    /** Age on 1 September for each class name. */
    private const AGES = [
        'Creche' => 2, 'Nursery 1' => 3, 'Nursery 2' => 4, 'KG 1' => 4, 'KG 2' => 5,
        'Primary 1' => 6, 'Primary 2' => 7, 'Primary 3' => 8, 'Primary 4' => 9, 'Primary 5' => 10, 'Primary 6' => 11,
        'JHS 1' => 12, 'JHS 2' => 13, 'JHS 3' => 14, 'SHS 1' => 15, 'SHS 2' => 16, 'SHS 3' => 17,
    ];

    private Randomizer $random;

    /** @var array<string, true> phones used in this school */
    private array $phones = [];

    public function __construct(
        private CurrentSchool $currentSchool,
        private AdmitStudent $admitStudent,
        private AddGuardian $addGuardian,
        private LinkGuardian $linkGuardian,
    ) {}

    public function run(): void
    {
        $school = $this->currentSchool->require();
        if (Student::exists()) {
            return; // already seeded: never add a second batch
        }

        $this->random = new Randomizer(new Mt19937(crc32($school->subdomain)));
        $this->phones = [];

        $streams = Stream::query()->select('streams.*')
            ->join('school_classes', 'school_classes.id', '=', 'streams.school_class_id')
            ->with('schoolClass')->orderBy('school_classes.sequence')->get()->values();
        $programmes = array_values(array_map(intval(...), Programme::orderBy('id')->pluck('id')->all()));
        $year = AcademicYear::current()->firstOrFail();
        $admittedOn = Term::where('academic_year_id', $year->id)->orderBy('starts_on')->firstOrFail()->starts_on;

        $target = (int) config('app.demo_students_per_school');
        $admitted = 0;
        while ($admitted < $target) {
            // Most families have one child here; some two, a few three.
            $children = min($target - $admitted, $this->pick([1, 1, 1, 1, 1, 1, 2, 2, 3]));
            $surname = $this->pick(self::SURNAMES);
            $guardians = [];

            for ($i = 0; $i < $children; $i++) {
                // Even spread over the streams, so every class has about the same number.
                $stream = $streams[$admitted % $streams->count()];
                $student = $this->admit($stream, $surname, $year, $admittedOn->toDateString(), $programmes);
                $guardians = $guardians === [] ? $this->addGuardians($student, $surname) : $this->linkSiblings($student, $guardians);
                $admitted++;
            }
        }
    }

    /**
     * @param  list<int>  $programmes
     */
    private function admit(Stream $stream, string $surname, AcademicYear $year, string $admittedOn, array $programmes): Student
    {
        $gender = $this->random->getInt(0, 1) === 1 ? 'male' : 'female';
        $first = $this->pick($gender === 'male' ? self::MALE : self::FEMALE);
        $dayName = $this->pick(self::DAY_NAMES[$gender]);
        $age = self::AGES[$stream->schoolClass->name] ?? 10;
        $born = $year->starts_on->subYears($age)->subDays($this->random->getInt(0, 364));

        return $this->admitStudent->handle([
            'first_name' => $first,
            'other_names' => $dayName !== $first && $this->random->getInt(0, 1) === 1 ? $dayName : '',
            'last_name' => $surname,
            'gender' => $gender,
            'date_of_birth' => $born->toDateString(),
            'admitted_on' => $admittedOn,
            'stream_id' => $stream->id,
            'programme_id' => $stream->schoolClass->level === SchoolLevel::Shs && $programmes !== [] ? $this->pick($programmes) : '',
        ]);
    }

    /**
     * A mother and usually a father; otherwise a grandparent or aunt/uncle. A few contacts only.
     *
     * @return list<array{guardian: Guardian, relationship: string, access_level: string}>
     */
    private function addGuardians(Student $student, string $surname): array
    {
        $roll = $this->random->getInt(1, 100);
        $people = match (true) {
            $roll <= 60 => [['mother', 'female'], ['father', 'male']],
            $roll <= 85 => [['mother', 'female']],
            $roll <= 93 => [['father', 'male']],
            default => [['grandparent', 'female'], ['aunt_uncle', 'male']],
        };

        $added = [];
        foreach ($people as [$relationship, $gender]) {
            $first = $this->pick($gender === 'male' ? self::MALE : self::FEMALE);
            $access = $relationship === 'aunt_uncle' && $this->random->getInt(0, 1) === 1 ? 'none' : 'full';
            $guardian = $this->addGuardian->handle($student, [
                'name' => "{$first} {$surname}",
                'phone' => $this->phone(),
                'relationship' => $relationship,
                'access_level' => $access,
            ]);
            $added[] = ['guardian' => $guardian, 'relationship' => $relationship, 'access_level' => $access];
        }

        return $added;
    }

    /**
     * Brothers and sisters share the first child's guardians (one record per person).
     *
     * @param  list<array{guardian: Guardian, relationship: string, access_level: string}>  $guardians
     * @return list<array{guardian: Guardian, relationship: string, access_level: string}>
     */
    private function linkSiblings(Student $student, array $guardians): array
    {
        foreach ($guardians as $link) {
            $this->linkGuardian->handle($student, $link['guardian'], [
                'relationship' => $link['relationship'],
                'access_level' => $link['access_level'],
            ]);
        }

        return $guardians;
    }

    /**
     * A made-up Ghanaian mobile number, unique within the school, typed the local way (024…).
     */
    private function phone(): string
    {
        do {
            $phone = '0'.$this->pick(self::PREFIXES).str_pad((string) $this->random->getInt(0, 9_999_999), 7, '0', STR_PAD_LEFT);
        } while (isset($this->phones[$phone]));
        $this->phones[$phone] = true;

        return $phone;
    }

    /**
     * @template T
     *
     * @param  non-empty-array<int, T>  $items
     * @return T
     */
    private function pick(array $items): mixed
    {
        return $items[$this->random->pickArrayKeys($items, 1)[0]];
    }
}
