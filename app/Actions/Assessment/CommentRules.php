<?php

namespace App\Actions\Assessment;

use App\Models\AssessmentComment;
use App\Models\Stream;
use App\Models\Subject;
use App\Models\Term;

/**
 * Early-years comment sheets: which comments exist and what is still missing before submit.
 * Who may write them is TaskRules::canManage() (which lets the class teacher write every area).
 * Queries are school-scoped.
 */
final class CommentRules
{
    public const MAX_LENGTH = 300;

    /**
     * @return array<int, AssessmentComment> keyed by student id
     */
    public static function comments(Term $term, Stream $stream, Subject $subject): array
    {
        return AssessmentComment::where(['term_id' => $term->id, 'stream_id' => $stream->id, 'subject_id' => $subject->id])
            ->get()->keyBy('student_id')->all();
    }

    /**
     * @return list<string>
     */
    public static function problems(Term $term, Stream $stream, Subject $subject): array
    {
        $rows = ScoreRules::roster($term, $stream, $subject);
        if ($rows === []) {
            return ['There are no children on this sheet.'];
        }

        $comments = self::comments($term, $stream, $subject);
        $missing = [];
        foreach ($rows as $row) {
            if ($row->editable && ! isset($comments[$row->student->id])) {
                $missing[] = "{$row->student->full_name} has no comment yet.";
            }
        }

        if (count($missing) > SheetLock::MAX_PROBLEMS) {
            $more = count($missing) - SheetLock::MAX_PROBLEMS;
            $missing = [...array_slice($missing, 0, SheetLock::MAX_PROBLEMS), "…and {$more} more ".($more === 1 ? 'child' : 'children').' without a comment.'];
        }

        return $missing;
    }
}
