<?php

namespace App\Actions\Assessment;

use App\Enums\EnrolmentStatus;
use App\Enums\SchoolLevel;
use App\Enums\SubjectType;
use App\Models\Enrolment;
use App\Models\ProgrammeSubject;
use App\Models\Stream;
use App\Models\Subject;
use App\Models\Term;
use App\Support\Assessment\SheetRow;

/**
 * Which students are on a class's mark sheet for a subject and term. Queries are school-scoped.
 *
 * Everyone enrolled in the stream at any time during the term. A student who left or moved to
 * another class during the term stays on the sheet, read-only, so their marks are kept. For an
 * SHS elective, only students whose programme includes the subject.
 */
final class ScoreRules
{
    /**
     * @return list<SheetRow>
     */
    public static function roster(Term $term, Stream $stream, Subject $subject): array
    {
        $query = Enrolment::query()->select('enrolments.*')
            ->join('students', 'students.id', '=', 'enrolments.student_id')
            ->with('student')
            ->where('enrolments.academic_year_id', $term->academic_year_id)
            ->where('enrolments.stream_id', $stream->id)
            ->where('enrolments.starts_on', '<=', $term->ends_on->toDateString())
            ->where(fn ($q) => $q->whereNull('enrolments.ends_on')->orWhere('enrolments.ends_on', '>', $term->starts_on->toDateString()))
            ->orderBy('students.last_name')->orderBy('students.first_name')->orderBy('enrolments.starts_on');

        $programmes = self::electiveProgrammes($stream, $subject);
        if ($programmes !== null) {
            $query->whereIn('enrolments.programme_id', $programmes);
        }

        $rows = [];
        foreach ($query->get() as $enrolment) {
            $left = $enrolment->ends_on !== null && $enrolment->ends_on->lte($term->ends_on);
            $note = match (true) {
                ! $left => null,
                $enrolment->status === EnrolmentStatus::Moved => 'Moved to another class '.$enrolment->ends_on->format('j M'),
                default => 'Left '.$enrolment->ends_on->format('j M'),
            };

            // A student who left and came back has two enrolments: keep the later one.
            $rows[$enrolment->student_id] = new SheetRow($enrolment->student, $enrolment, ! $left, $note);
        }

        return array_values($rows);
    }

    public static function rowFor(Term $term, Stream $stream, Subject $subject, int $studentId): ?SheetRow
    {
        foreach (self::roster($term, $stream, $subject) as $row) {
            if ($row->student->id === $studentId) {
                return $row;
            }
        }

        return null;
    }

    /**
     * The programmes whose students take this subject, when it is an SHS elective; null when
     * everyone in the class takes it.
     *
     * @return list<int>|null
     */
    private static function electiveProgrammes(Stream $stream, Subject $subject): ?array
    {
        if ($stream->schoolClass->level !== SchoolLevel::Shs) {
            return null;
        }

        $links = ProgrammeSubject::where('subject_id', $subject->id)->get();
        if (! $links->contains(fn (ProgrammeSubject $l) => $l->type === SubjectType::Elective)) {
            return null;
        }

        return array_values(array_map(intval(...), $links->pluck('programme_id')->unique()->all()));
    }
}
