<?php

namespace App\Actions\Results;

use App\Actions\Assessment\TaskRules;
use App\Enums\Conduct;
use App\Enums\PermissionKey;
use App\Enums\ReportCardStatus;
use App\Models\Enrolment;
use App\Models\ReportCard;
use App\Models\Stream;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Saves the words on a student's report card. The class teacher of the stream (or a head)
 * writes the teacher's remark, conduct and interest; only a head writes the head's remark.
 * Nothing changes once the report card is approved or published.
 */
class SaveReportRemarks
{
    /** Fields a class teacher may write; the head may also write head_remark. */
    public const TEACHER_FIELDS = ['teacher_remark', 'conduct', 'interest'];

    public const LIMITS = ['teacher_remark' => 400, 'interest' => 100, 'head_remark' => 400];

    /**
     * @param  array<string, string|null>  $fields  any of teacher_remark, conduct, interest, head_remark
     */
    public function handle(User $by, Term $term, Stream $stream, Student $student, array $fields): ReportCard
    {
        $isHead = $by->can(PermissionKey::GradesApprove->value);
        $isClassTeacher = in_array($stream->id, TaskRules::classTeacherStreamIds($by, $term->academic_year_id), true);

        if (! $isHead && ! $isClassTeacher) {
            throw new AuthorizationException('Only the class teacher or the head can write remarks for this class.');
        }
        if (array_key_exists('head_remark', $fields) && ! $isHead) {
            throw new AuthorizationException('Only the head can write the head\'s remark.');
        }

        $inClass = Enrolment::where(['academic_year_id' => $term->academic_year_id, 'stream_id' => $stream->id, 'student_id' => $student->id])
            ->where('starts_on', '<=', $term->ends_on->toDateString())
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>', $term->ends_on->toDateString()))
            ->exists();
        if (! $inClass) {
            throw ValidationException::withMessages(['remark' => "{$student->full_name} is not in {$stream->full_name} this term."]);
        }

        $card = ReportCard::firstOrNew(['term_id' => $term->id, 'student_id' => $student->id]);
        if (in_array($card->status, [ReportCardStatus::Approved, ReportCardStatus::Published], true)) {
            throw ValidationException::withMessages(['remark' => "{$student->full_name}'s report card is already {$card->status->label()}. The head can withdraw it to change remarks."]);
        }

        $clean = [];
        foreach ([...self::TEACHER_FIELDS, 'head_remark'] as $field) {
            if (! array_key_exists($field, $fields)) {
                continue;
            }
            $value = preg_replace('/\s+/', ' ', trim((string) $fields[$field])) ?? '';

            if ($field === 'conduct') {
                if ($value !== '' && Conduct::tryFrom($value) === null) {
                    throw ValidationException::withMessages(['conduct' => 'Choose a conduct from the list.']);
                }
            } elseif (mb_strlen($value) > self::LIMITS[$field]) {
                throw ValidationException::withMessages([$field => 'Keep it to '.self::LIMITS[$field].' characters.']);
            }

            $clean[$field] = $value === '' ? null : $value;
        }

        if (! $card->exists) {
            $card->fill(['stream_id' => $stream->id, 'status' => ReportCardStatus::Draft]);
        }
        $card->fill($clean)->save();

        return $card;
    }
}
