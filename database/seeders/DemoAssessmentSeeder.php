<?php

namespace Database\Seeders;

use App\Actions\Assessment\ScoreRules;
use App\Actions\Assessment\SetUpDefaultSchemes;
use App\Actions\Subjects\GenerateDefaultSubjects;
use App\Enums\AssessmentMode;
use App\Enums\ComponentKind;
use App\Enums\SchoolLevel;
use App\Enums\ScoreStatus;
use App\Enums\SheetStatus;
use App\Models\AcademicYear;
use App\Models\AssessmentComment;
use App\Models\AssessmentComponent;
use App\Models\AssessmentScheme;
use App\Models\AssessmentScore;
use App\Models\AssessmentSheet;
use App\Models\AssessmentTask;
use App\Models\ClassSubject;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Stream;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Support\Assessment\SheetRow;
use App\Support\Tenancy\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;

/**
 * A realistic term of assessment for each demo school (run by DatabaseSeeder after the schools):
 * tasks under every part, marks for every student, early-years comments, and sheets in every
 * state, so the Marks, Progress and Comments screens can be tried without typing.
 *
 * Mostly submitted, some ready, some in progress, a few not started and one reopened. The demo
 * teacher's Primary 4 Mathematics is left part-filled for them to finish and submit.
 *
 * Fast: marks and comments are written in bulk (no activity log, which is right for demo
 * data). Safe to run again: any sheet that already has tasks or comments is left alone.
 */
class DemoAssessmentSeeder extends Seeder
{
    /** Tasks per part: name, out of. The exam part takes one. */
    private const TASKS = [
        ComponentKind::ClassWork->value => [['Class exercise 1', 10], ['Class exercise 2', 10]],
        ComponentKind::Test->value => [['Class test 1', 20], ['Class test 2', 20]],
        ComponentKind::Project->value => [['Term project', 30]],
        ComponentKind::Homework->value => [['Homework 1', 10]],
        ComponentKind::Exam->value => [['End of term exam', 100]],
        ComponentKind::Other->value => [['Assessment 1', 20]],
    ];

    /** @var array<string, list<string>> early-years area => comments to pick from */
    private const COMMENTS = [
        'Numeracy' => ['Counts confidently to 20 and recognises numbers 1 to 10.', 'Sorts objects by size and colour; still building confidence with counting past 10.', 'Enjoys number songs and can add two small groups of objects.'],
        'Early Numeracy' => ['Counts to 10 with support and enjoys matching shapes.', 'Recognises numbers 1 to 5 and sorts toys by colour.', 'Beginning to count objects one by one.'],
        'Language and Literacy' => ['Knows most letter sounds and is beginning to blend simple words.', 'Listens well to stories and retells them in their own words.', 'Writes their name and is growing in confidence when speaking.'],
        'Communication and Language' => ['Speaks in short sentences and listens well at story time.', 'Follows simple instructions and is learning new words every week.', 'Beginning to join in songs and rhymes.'],
        'Personal, Social and Emotional Development' => ['Plays kindly with others and takes turns.', 'Settling in well and growing in independence.', 'Shares toys and is learning to manage their feelings.'],
    ];

    private const GENERAL_COMMENTS = [
        'Takes part eagerly and is making steady progress.',
        'Works well with others; growing in confidence this term.',
        'Shows good effort; needs a little more support to finish tasks.',
        'Enjoys this area and shows lots of creativity.',
    ];

    private Randomizer $random;

    /** Separate from $random (marks), so each sheet's state is the same on every run. */
    private Randomizer $states;

    /** @var array<int, float> student id => how well they do, 0.4–0.95 */
    private array $ability = [];

    public function __construct(private CurrentSchool $currentSchool) {}

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoAssessmentSeeder must never run in production.');
        }

        foreach (School::whereIn('subdomain', ['greenhill', 'hillview'])->orderBy('subdomain')->get() as $school) {
            $this->currentSchool->runFor($school, fn () => $this->seedSchool($school));
        }
    }

    private function seedSchool(School $school): void
    {
        $term = Term::current()->first();
        $year = AcademicYear::current()->first();
        if ($term === null || $year === null) {
            return;
        }

        $this->random = new Randomizer(new Mt19937(crc32($school->subdomain.'-assessment')));
        $this->states = new Randomizer(new Mt19937(crc32($school->subdomain.'-sheet-states')));

        $this->earlyYearsAreas();
        app(SetUpDefaultSchemes::class)->handle($year);

        $schemes = AssessmentScheme::with('components')->where('academic_year_id', $year->id)->get()->keyBy(fn ($s) => $s->level->value);
        $subjectIdsByClass = ClassSubject::all()->groupBy('school_class_id')->map(fn ($l) => $l->pluck('subject_id')->all());
        $subjects = Subject::all()->keyBy('id');
        $head = User::where('school_id', $school->id)->where('email', 'admin@'.$school->subdomain.'.test')->first();
        $demoTeacherStream = SchoolClass::where('name', 'Primary 4')->first()?->streams()->orderBy('name')->first();

        $streams = Stream::with('schoolClass')->get()->sortBy(fn (Stream $s) => [$s->schoolClass->sequence, $s->name]);
        foreach ($streams as $stream) {
            $scheme = $schemes->get($stream->schoolClass->level->value);
            if ($scheme === null) {
                continue;
            }

            foreach ($subjectIdsByClass->get($stream->school_class_id, []) as $subjectId) {
                $subject = $subjects->get($subjectId);
                if ($subject === null) {
                    continue;
                }

                // Every sheet draws its state first, in a fixed order, from its own generator, so a
                // second run makes the same choices and skips what is already there.
                // The demo teacher's own Mathematics is left part-filled, to finish and submit.
                $state = $this->pickState();
                if ($stream->is($demoTeacherStream) && $subject->name === 'Mathematics') {
                    $state = 'in_progress';
                }
                if ($state === 'not_started' || $this->started($term, $stream, $subject)) {
                    continue;
                }

                $rows = array_values(array_filter(ScoreRules::roster($term, $stream, $subject), fn ($row) => $row->editable));
                if ($rows === []) {
                    continue;
                }

                $scheme->mode === AssessmentMode::Descriptive
                    ? $this->comments($term, $stream, $subject, $rows, $state)
                    : $this->marks($term, $stream, $subject, array_values($scheme->components->all()), $rows, $state);

                if ($state === 'submitted') {
                    $this->submit($term, $stream, $subject, $head);
                }
            }
        }

        // One sheet reopened by the head, so the "Unlocked for corrections" state can be seen.
        $first = AssessmentSheet::where('term_id', $term->id)->where('status', SheetStatus::Submitted)->orderBy('id')->first();
        if ($first !== null && ! AssessmentSheet::where('term_id', $term->id)->where('status', SheetStatus::Reopened)->exists()) {
            $first->update(['status' => SheetStatus::Reopened, 'unlocked_by' => $head?->id, 'unlocked_at' => now(), 'unlock_reason' => 'Exam marks for two students were swapped.']);
        }
    }

    /**
     * Creche and Nursery classes with no subjects get the early-years areas (a school that
     * chose its own areas keeps them).
     */
    private function earlyYearsAreas(): void
    {
        app(GenerateDefaultSubjects::class)->handle();

        foreach (SchoolClass::whereIn('level', [SchoolLevel::Creche, SchoolLevel::Nursery])->get() as $class) {
            if (ClassSubject::where('school_class_id', $class->id)->exists()) {
                continue;
            }
            foreach (Subject::all()->filter(fn (Subject $s) => $s->levels->contains($class->level)) as $subject) {
                ClassSubject::create(['school_class_id' => $class->id, 'subject_id' => $subject->id]);
            }
        }
    }

    private function started(Term $term, Stream $stream, Subject $subject): bool
    {
        $where = ['term_id' => $term->id, 'stream_id' => $stream->id, 'subject_id' => $subject->id];

        return AssessmentTask::where($where)->exists() || AssessmentComment::where($where)->exists();
    }

    /**
     * Most sheets submitted; some ready, some in progress, a few not started.
     */
    private function pickState(): string
    {
        $roll = $this->states->getInt(1, 100);

        return match (true) {
            $roll <= 55 => 'submitted',
            $roll <= 72 => 'ready',
            $roll <= 90 => 'in_progress',
            default => 'not_started',
        };
    }

    /**
     * @param  list<AssessmentComponent>  $parts
     * @param  list<SheetRow>  $rows
     */
    private function marks(Term $term, Stream $stream, Subject $subject, array $parts, array $rows, string $state): void
    {
        $now = now();
        $lastDay = CarbonImmutable::today()->min($term->ends_on);
        $tasks = [];

        foreach ($parts as $part) {
            $templates = self::TASKS[$part->kind->value];
            // In progress: the exam is often not set yet.
            if ($state === 'in_progress' && $part->kind === ComponentKind::Exam) {
                continue;
            }

            foreach ($templates as $i => [$name, $max]) {
                $given = $part->kind === ComponentKind::Exam
                    ? $term->ends_on->subDays(7)
                    : $term->starts_on->addDays(14 + ($i * 21) + $this->random->getInt(0, 6))->min($lastDay);

                $tasks[] = AssessmentTask::create([
                    'term_id' => $term->id, 'stream_id' => $stream->id, 'subject_id' => $subject->id,
                    'assessment_component_id' => $part->id, 'name' => $name, 'given_on' => $given, 'max_mark' => $max,
                ]);
            }
        }

        $scores = [];
        foreach ($rows as $row) {
            $ability = $this->ability[$row->student->id] ??= $this->random->getInt(40, 95) / 100;

            foreach ($tasks as $t => $task) {
                // In progress: the last task is only partly marked.
                if ($state === 'in_progress' && $t === count($tasks) - 1 && $this->random->getInt(1, 100) <= 55) {
                    continue;
                }

                $absent = $this->random->getInt(1, 100) <= 2;
                $mark = $absent ? null : max(0, min($task->max_mark, round(($ability + $this->random->getInt(-15, 15) / 100) * $task->max_mark * 2) / 2));

                $scores[] = [
                    'school_id' => $stream->school_id, 'assessment_task_id' => $task->id, 'student_id' => $row->student->id,
                    'status' => ($absent ? ScoreStatus::Absent : ScoreStatus::Marked)->value,
                    'mark' => $mark === null ? null : number_format($mark, 1, '.', ''),
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($scores, 500) as $chunk) {
            AssessmentScore::insert($chunk);
        }
    }

    /**
     * @param  list<SheetRow>  $rows
     */
    private function comments(Term $term, Stream $stream, Subject $subject, array $rows, string $state): void
    {
        $bank = self::COMMENTS[$subject->name] ?? self::GENERAL_COMMENTS;
        $now = now();
        $comments = [];

        foreach ($rows as $i => $row) {
            if ($state === 'in_progress' && $i >= intdiv(count($rows), 2)) {
                break;
            }
            $comments[] = [
                'school_id' => $stream->school_id, 'term_id' => $term->id, 'stream_id' => $stream->id, 'subject_id' => $subject->id,
                'student_id' => $row->student->id, 'comment' => $bank[$this->random->getInt(0, count($bank) - 1)],
                'created_at' => $now, 'updated_at' => $now,
            ];
        }

        foreach (array_chunk($comments, 500) as $chunk) {
            AssessmentComment::insert($chunk);
        }
    }

    private function submit(Term $term, Stream $stream, Subject $subject, ?User $by): void
    {
        AssessmentSheet::create([
            'term_id' => $term->id, 'stream_id' => $stream->id, 'subject_id' => $subject->id,
            'status' => SheetStatus::Submitted, 'submitted_by' => $by?->id,
            'submitted_at' => $term->ends_on->subDays(5)->min(CarbonImmutable::now()),
        ]);
    }
}
