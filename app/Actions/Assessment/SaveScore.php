<?php

namespace App\Actions\Assessment;

use App\Enums\ScoreStatus;
use App\Models\AssessmentScore;
use App\Models\AssessmentTask;
use App\Models\Student;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Saves what a teacher typed in one mark-sheet cell: a mark (halves allowed) from 0 to the
 * task's "out of", "A" for Absent, "X" for Exempt, or nothing to clear the cell.
 * Only the subject's teacher in that class, or a head, may enter marks.
 */
class SaveScore
{
    public function handle(User $by, AssessmentTask $task, Student $student, string $input): ?AssessmentScore
    {
        $task->loadMissing(['term', 'stream.schoolClass', 'subject']);
        TaskRules::authorize($by, $task->stream, $task->subject, $task->term->academic_year_id);

        $row = ScoreRules::rowFor($task->term, $task->stream, $task->subject, $student->id);
        if ($row === null) {
            throw ValidationException::withMessages(['score' => "{$student->full_name} is not on this class's list for {$task->subject->name} this term."]);
        }
        if (! $row->editable) {
            throw ValidationException::withMessages(['score' => "{$student->full_name}: {$row->note}, so their marks cannot change here."]);
        }

        [$status, $mark] = self::parse($input, $task->max_mark);
        $existing = AssessmentScore::where(['assessment_task_id' => $task->id, 'student_id' => $student->id])->first();

        if ($status === null) {
            $existing?->delete();

            return null;
        }

        $score = $existing ?? new AssessmentScore(['assessment_task_id' => $task->id, 'student_id' => $student->id]);
        $score->status = $status;
        $score->mark = $mark;

        if ($score->isDirty()) {
            $score->entered_by = $by->id;
            $score->save();
        }

        return $score;
    }

    /**
     * @return array{0: ScoreStatus|null, 1: string|null} status (null = clear) and mark
     */
    public static function parse(string $input, int $maxMark): array
    {
        $value = str_replace(',', '.', trim($input));

        return match (true) {
            $value === '' => [null, null],
            in_array(strtolower($value), ['a', 'abs', 'absent'], true) => [ScoreStatus::Absent, null],
            in_array(strtolower($value), ['x', 'e', 'exempt'], true) => [ScoreStatus::Exempt, null],
            default => [ScoreStatus::Marked, self::mark($value, $maxMark)],
        };
    }

    private static function mark(string $value, int $maxMark): string
    {
        if (preg_match('/^\d{1,4}(\.\d+)?$/', $value) !== 1) {
            throw ValidationException::withMessages(['score' => 'Type a mark, A for absent or X for exempt.']);
        }

        $halves = (float) $value * 2;
        if (abs($halves - round($halves)) > 1e-9) {
            throw ValidationException::withMessages(['score' => 'Marks go in whole or half marks, e.g. 12 or 12.5.']);
        }
        if ($halves > $maxMark * 2) {
            throw ValidationException::withMessages(['score' => "The task is out of {$maxMark}."]);
        }

        return number_format(round($halves) / 2, 1, '.', '');
    }
}
