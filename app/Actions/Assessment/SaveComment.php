<?php

namespace App\Actions\Assessment;

use App\Models\AssessmentComment;
use App\Models\Stream;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Saves a teacher's comment on one child in one early-years learning area, or clears it when
 * blank. The class teacher, a teacher assigned the area, or a head; not while submitted. Once
 * the sheet has been submitted, every save is logged, first entries too.
 */
class SaveComment
{
    public function handle(User $by, Term $term, Stream $stream, Subject $subject, Student $student, string $text): ?AssessmentComment
    {
        TaskRules::authorize($by, $stream, $subject, $term->academic_year_id);
        if (! TaskRules::isCommentsOnly($stream, $term->academic_year_id)) {
            throw ValidationException::withMessages(['comment' => "{$stream->schoolClass->level->label()} is assessed with marks, not comments."]);
        }
        SheetLock::ensureOpen($term->id, $stream->id, $subject->id, 'comment');

        $row = ScoreRules::rowFor($term, $stream, $subject, $student->id);
        if ($row === null) {
            throw ValidationException::withMessages(['comment' => "{$student->full_name} is not in {$stream->full_name} this term."]);
        }
        if (! $row->editable) {
            throw ValidationException::withMessages(['comment' => "{$student->full_name}: {$row->note}, so this comment cannot change here."]);
        }

        $text = preg_replace('/[ \t]+/', ' ', trim($text)) ?? '';
        if (mb_strlen($text) > CommentRules::MAX_LENGTH) {
            throw ValidationException::withMessages(['comment' => 'Keep it to '.CommentRules::MAX_LENGTH.' characters, about two sentences.']);
        }

        $existing = AssessmentComment::where(['term_id' => $term->id, 'stream_id' => $stream->id, 'subject_id' => $subject->id, 'student_id' => $student->id])->first();

        if ($text === '') {
            $existing?->delete();

            return null;
        }

        $comment = $existing ?? new AssessmentComment(['term_id' => $term->id, 'stream_id' => $stream->id, 'subject_id' => $subject->id, 'student_id' => $student->id]);
        $comment->comment = $text;

        if ($comment->isDirty()) {
            $comment->entered_by = $by->id;
            $comment->save();

            if ($comment->wasRecentlyCreated && SheetLock::find($term->id, $stream->id, $subject->id) !== null) {
                activity('grades')->performedOn($comment)->causedBy($by)->event('created')
                    ->withProperties(['attributes' => ['student_id' => $student->id, 'subject_id' => $subject->id, 'comment' => $text]])
                    ->log('created after submit');
            }
        }

        return $comment;
    }
}
