<?php

namespace App\Support\Results;

use App\Actions\Assessment\TaskRules;
use App\Enums\Conduct;
use App\Enums\SchoolLevel;
use App\Models\AssessmentComment;
use App\Models\ClassTeacherAssignment;
use App\Models\GradeScale;
use App\Models\ReportCard;
use App\Models\School;
use App\Models\Term;
use App\Models\TermResult;
use App\Support\Pdf\SchoolLogo;
use App\Support\Settings\SchoolSettings;
use Illuminate\Support\Collection;

/**
 * Everything printed on one report card, gathered once so the templates only lay it out.
 */
final readonly class ReportCardData
{
    /**
     * @param  Collection<int, TermResult>  $results  marked levels, by subject name
     * @param  Collection<int, AssessmentComment>  $comments  early years, by subject name
     * @param  array{present: int, total: int}  $attendance
     */
    public function __construct(
        public School $school,
        public ?string $logo,
        public ReportCard $card,
        public Term $term,
        public SchoolLevel $level,
        public string $template,
        public Collection $results,
        public Collection $comments,
        public ?GradeScale $scale,
        public array $attendance,
        public ?Term $nextTerm,
        public ?string $classTeacher,
        public bool $showPositions,
        public ?string $conduct,
    ) {}

    public static function for(School $school, ReportCard $card): self
    {
        $card->loadMissing(['student', 'stream.schoolClass', 'term.academicYear']);
        $term = $card->term;
        $level = $card->stream->schoolClass->level;
        $commentsOnly = TaskRules::isCommentsOnly($card->stream, $term->academic_year_id);

        $results = $commentsOnly ? collect() : TermResult::with('subject')
            ->where(['term_id' => $term->id, 'stream_id' => $card->stream_id, 'student_id' => $card->student_id])->get()
            ->sortBy(fn (TermResult $r) => $r->subject->name)->values();
        $comments = $commentsOnly ? AssessmentComment::with('subject')
            ->where(['term_id' => $term->id, 'stream_id' => $card->stream_id, 'student_id' => $card->student_id])->get()
            ->sortBy(fn (AssessmentComment $c) => $c->subject->name)->values() : collect();

        $template = match (true) {
            $commentsOnly => 'early-years',
            $level === SchoolLevel::Jhs, $level === SchoolLevel::Shs => 'secondary',
            default => 'primary',
        };

        return new self(
            school: $school,
            logo: SchoolLogo::dataUri($school->logo_path),
            card: $card,
            term: $term,
            level: $level,
            template: $template,
            results: $results,
            comments: $comments,
            scale: $commentsOnly ? null : GradeScale::with('bands')->where('level', $level)->first(),
            attendance: AttendanceSummary::for($term, [$card->student_id])[$card->student_id],
            nextTerm: Term::where('starts_on', '>', $term->ends_on->toDateString())->orderBy('starts_on')->first(),
            classTeacher: ClassTeacherAssignment::with('staff')->where(['academic_year_id' => $term->academic_year_id, 'stream_id' => $card->stream_id])->first()?->staff->full_name,
            showPositions: (bool) SchoolSettings::for($school)->get('show_positions', false),
            conduct: Conduct::tryFrom((string) $card->conduct)?->label(),
        );
    }

    /**
     * The continuous-assessment part of a result (every part but the exam) and the exam part,
     * each as [score, out of].
     *
     * @return array{class: array{0: float|null, 1: int}, exam: array{0: float|null, 1: int}}
     */
    public static function split(TermResult $result): array
    {
        $class = [null, 0];
        $exam = [null, 0];
        foreach ($result->parts as $part) {
            $isExam = ($part['kind'] ?? null) === 'exam';
            $slot = $isExam ? $exam : $class;
            $slot[1] += $part['weight'];
            if ($part['score'] !== null) {
                $slot[0] = ($slot[0] ?? 0) + $part['score'];
            }
            $isExam ? $exam = $slot : $class = $slot;
        }

        return ['class' => $class, 'exam' => $exam];
    }
}
