<?php

namespace App\Actions\Assessment;

use App\Enums\AssessmentMode;
use App\Enums\SchoolLevel;
use App\Enums\SheetProgress;
use App\Enums\SheetStatus;
use App\Enums\SubjectType;
use App\Models\AssessmentScheme;
use App\Models\AssessmentScore;
use App\Models\AssessmentSheet;
use App\Models\AssessmentTask;
use App\Models\ClassSubject;
use App\Models\Enrolment;
use App\Models\ProgrammeSubject;
use App\Models\Stream;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Support\Assessment\ProgressCell;
use App\Support\Assessment\ProgressOverview;

/**
 * The head's overview of every class and subject's mark sheet in a term.
 *
 * A fixed number of queries whatever the school's size: boxes filled are counted per class,
 * subject and student in one grouped query, instead of building each sheet. "Ready" uses the
 * same rules as SheetLock::problems(): a task in every part and a mark, A or X for every student
 * still in the class (an SHS elective: students whose programme takes it).
 */
class BuildProgressOverview
{
    public function handle(Term $term): ProgressOverview
    {
        $streams = Stream::query()->select('streams.*')
            ->join('school_classes', 'school_classes.id', '=', 'streams.school_class_id')
            ->with('schoolClass')
            ->orderBy('school_classes.sequence')->orderBy('streams.name')
            ->get();
        $subjects = Subject::orderBy('name')->get()->keyBy('id');
        $subjectIdsByClass = ClassSubject::all()->groupBy('school_class_id')->map(fn ($links) => $links->pluck('subject_id')->all());

        $teachers = TeachingAssignment::with('staff')->where('academic_year_id', $term->academic_year_id)->get()
            ->keyBy(fn (TeachingAssignment $a) => "{$a->stream_id}-{$a->subject_id}");
        $schemes = AssessmentScheme::with('components')->where('academic_year_id', $term->academic_year_id)->get()
            ->keyBy(fn (AssessmentScheme $s) => $s->level->value);
        $records = AssessmentSheet::where('term_id', $term->id)->get()
            ->keyBy(fn (AssessmentSheet $s) => "{$s->stream_id}-{$s->subject_id}");

        // Tasks per class and subject, and which parts they cover.
        $taskCounts = [];
        $partsCovered = [];
        foreach (AssessmentTask::where('term_id', $term->id)
            ->selectRaw('stream_id, subject_id, assessment_component_id, COUNT(*) AS n')
            ->groupBy('stream_id', 'subject_id', 'assessment_component_id')->toBase()->get() as $row) {
            $key = "{$row->stream_id}-{$row->subject_id}";
            $taskCounts[$key] = ($taskCounts[$key] ?? 0) + (int) $row->n;
            $partsCovered[$key][(int) $row->assessment_component_id] = true;
        }

        // Students still in each class this term (those who left do not count), with programme.
        $roster = [];
        foreach (Enrolment::where('academic_year_id', $term->academic_year_id)
            ->where('starts_on', '<=', $term->ends_on->toDateString())
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>', $term->ends_on->toDateString()))
            ->toBase()->get(['stream_id', 'student_id', 'programme_id']) as $row) {
            $roster[(int) $row->stream_id][(int) $row->student_id] = $row->programme_id === null ? null : (int) $row->programme_id;
        }

        // SHS electives: subject id => the programmes that take it.
        $electiveProgrammes = [];
        $links = ProgrammeSubject::all();
        foreach ($links->where('type', SubjectType::Elective)->pluck('subject_id')->unique() as $subjectId) {
            $electiveProgrammes[(int) $subjectId] = array_map(intval(...), $links->where('subject_id', $subjectId)->pluck('programme_id')->all());
        }

        // Boxes filled, per class, subject and student.
        $filled = [];
        foreach (AssessmentScore::query()
            ->join('assessment_tasks', 'assessment_tasks.id', '=', 'assessment_scores.assessment_task_id')
            ->where('assessment_tasks.term_id', $term->id)
            ->selectRaw('assessment_tasks.stream_id, assessment_tasks.subject_id, assessment_scores.student_id, COUNT(*) AS n')
            ->groupBy('assessment_tasks.stream_id', 'assessment_tasks.subject_id', 'assessment_scores.student_id')
            ->toBase()->get() as $row) {
            $filled["{$row->stream_id}-{$row->subject_id}"][(int) $row->student_id] = (int) $row->n;
        }

        $levels = [];
        $cellsByStream = [];
        foreach ($streams->groupBy(fn (Stream $s) => $s->schoolClass->level->value) as $levelValue => $levelStreams) {
            $level = SchoolLevel::from((string) $levelValue);
            $scheme = $schemes->get($level->value);
            $commentsOnly = $scheme === null || $scheme->mode === AssessmentMode::Descriptive;
            $levelSubjectIds = [];

            foreach ($levelStreams as $stream) {
                $taken = $subjectIdsByClass->get($stream->school_class_id, []);
                $levelSubjectIds = [...$levelSubjectIds, ...$taken];
                if ($commentsOnly) {
                    continue;
                }

                foreach ($taken as $subjectId) {
                    $subject = $subjects->get($subjectId);
                    if ($subject === null) {
                        continue;
                    }
                    $key = "{$stream->id}-{$subject->id}";

                    $students = $roster[$stream->id] ?? [];
                    if ($level === SchoolLevel::Shs && isset($electiveProgrammes[$subject->id])) {
                        $students = array_filter($students, fn (?int $programme) => in_array($programme, $electiveProgrammes[$subject->id], true));
                    }

                    $tasks = $taskCounts[$key] ?? 0;
                    $expected = $tasks * count($students);
                    $done = 0;
                    foreach (array_keys($students) as $studentId) {
                        $done += min($tasks, $filled[$key][$studentId] ?? 0);
                    }
                    $allParts = $scheme->components->every(fn ($part) => isset($partsCovered[$key][$part->id]));

                    $progress = match (true) {
                        $records->get($key)?->status === SheetStatus::Submitted => SheetProgress::Submitted,
                        $records->get($key)?->status === SheetStatus::Reopened => SheetProgress::Reopened,
                        $tasks === 0 => SheetProgress::NotStarted,
                        $allParts && $students !== [] && $done === $expected => SheetProgress::Ready,
                        default => SheetProgress::InProgress,
                    };

                    $assignment = $teachers->get($key);
                    $cellsByStream[$stream->id][] = new ProgressCell($stream, $subject, $progress, $assignment?->staff->full_name, $assignment?->staff_id, $done, $expected);
                }
            }

            $levels[] = [
                'level' => $level,
                'streams' => array_values($levelStreams->all()),
                'subjects' => array_values($subjects->only(array_unique($levelSubjectIds))->sortBy('name')->all()),
                // Why a level has no sheets: comments only, or no assessment set up for the year.
                'note' => match (true) {
                    $scheme === null => 'Assessment is not set up for this level yet.',
                    $commentsOnly => 'Assessed with comments only.',
                    default => null,
                },
            ];
        }

        return new ProgressOverview($levels, $cellsByStream);
    }
}
