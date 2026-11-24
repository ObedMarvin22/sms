<?php

namespace App\Actions\Assessment;

use App\Enums\AssessmentMode;
use App\Enums\ComponentKind;
use App\Enums\PermissionKey;
use App\Models\AssessmentComponent;
use App\Models\AssessmentScheme;
use App\Models\AssessmentTask;
use App\Models\ClassSubject;
use App\Models\ClassTeacherAssignment;
use App\Models\Stream;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Who may set tasks for which class and subject, and what a valid task is.
 * Subject teachers (grades.enter) manage the subjects and streams they teach this year;
 * heads (grades.approve) manage any subject a class takes. A class teacher may look at every
 * subject's sheet for their own class without changing it. Queries are school-scoped.
 */
final class TaskRules
{
    public const MAX_MARK = 1000;

    public static function canManage(User $user, Stream $stream, Subject $subject, int $yearId): bool
    {
        if (! ClassSubject::where('school_class_id', $stream->school_class_id)->where('subject_id', $subject->id)->exists()) {
            return false;
        }
        if ($user->can(PermissionKey::GradesApprove->value)) {
            return true;
        }

        if (! $user->can(PermissionKey::GradesEnter->value)) {
            return false;
        }

        $teaches = TeachingAssignment::query()
            ->where(['academic_year_id' => $yearId, 'stream_id' => $stream->id, 'subject_id' => $subject->id])
            ->whereHas('staff', fn ($q) => $q->where('user_id', $user->id))
            ->exists();

        // Early years: the class teacher usually teaches everything, so they may write the
        // comments for every learning area without a teaching assignment for each.
        return $teaches || (self::isCommentsOnly($stream, $yearId) && in_array($stream->id, self::classTeacherStreamIds($user, $yearId), true));
    }

    /**
     * The stream's level is assessed with comments (Creche, Nursery, KG by default) this year.
     */
    public static function isCommentsOnly(Stream $stream, int $yearId): bool
    {
        return AssessmentScheme::where('academic_year_id', $yearId)
            ->where('level', $stream->schoolClass->level)
            ->where('mode', AssessmentMode::Descriptive)
            ->exists();
    }

    /**
     * Manage, or look as the stream's class teacher this year.
     */
    public static function canView(User $user, Stream $stream, Subject $subject, int $yearId): bool
    {
        return self::canManage($user, $stream, $subject, $yearId)
            || ($user->can(PermissionKey::GradesEnter->value)
                && in_array($stream->id, self::classTeacherStreamIds($user, $yearId), true)
                && ClassSubject::where('school_class_id', $stream->school_class_id)->where('subject_id', $subject->id)->exists());
    }

    /**
     * @return list<int>
     */
    public static function classTeacherStreamIds(User $user, int $yearId): array
    {
        return array_values(array_map(intval(...), ClassTeacherAssignment::query()
            ->where('academic_year_id', $yearId)
            ->whereHas('staff', fn ($q) => $q->where('user_id', $user->id))
            ->pluck('stream_id')->all()));
    }

    /**
     * @throws AuthorizationException
     */
    public static function authorize(User $user, Stream $stream, Subject $subject, int $yearId): void
    {
        if (! self::canManage($user, $stream, $subject, $yearId)) {
            throw new AuthorizationException('You do not teach this subject in this class.');
        }
    }

    /**
     * The class and subject pairs this user may open in a year, in class order. `manage` is
     * false for a class teacher looking at another teacher's subject.
     *
     * @return Collection<int, array{stream: Stream, subject: Subject, teacher: string|null, manage: bool, comments: bool}>
     */
    public static function sheetsFor(User $user, int $yearId): Collection
    {
        $approver = $user->can(PermissionKey::GradesApprove->value);
        if (! $approver && ! $user->can(PermissionKey::GradesEnter->value)) {
            return collect();
        }

        $assignments = TeachingAssignment::with('staff')->where('academic_year_id', $yearId)->get()
            ->keyBy(fn (TeachingAssignment $a) => "{$a->stream_id}-{$a->subject_id}");
        $subjects = Subject::orderBy('name')->get();
        $subjectIdsByClass = ClassSubject::all()->groupBy('school_class_id')
            ->map(fn ($links) => $links->pluck('subject_id')->all());
        $myStreams = $approver ? [] : self::classTeacherStreamIds($user, $yearId);
        $commentLevels = AssessmentScheme::where('academic_year_id', $yearId)->where('mode', AssessmentMode::Descriptive)
            ->pluck('level')->all();

        // Streams in class order, then subjects by name: the order of the list on screen.
        $sheets = [];
        foreach (self::streams() as $stream) {
            $taken = $subjectIdsByClass->get($stream->school_class_id, []);

            foreach ($subjects as $subject) {
                $assignment = $assignments->get("{$stream->id}-{$subject->id}");
                $mine = $assignment !== null && $assignment->staff->user_id === $user->id;

                $classTeacher = in_array($stream->id, $myStreams, true);

                if (in_array($subject->id, $taken, true) && ($approver || $mine || $classTeacher)) {
                    $sheets[] = ['stream' => $stream, 'subject' => $subject, 'teacher' => $assignment?->staff->full_name, 'manage' => $approver || $mine || ($classTeacher && in_array($stream->schoolClass->level, $commentLevels, true)), 'comments' => in_array($stream->schoolClass->level, $commentLevels, true)];
                }
            }
        }

        return collect($sheets);
    }

    public static function schemeFor(Stream $stream, int $yearId): ?AssessmentScheme
    {
        return AssessmentScheme::with('components')
            ->where('academic_year_id', $yearId)
            ->where('level', $stream->schoolClass->level)
            ->first();
    }

    /**
     * @param  array{name: string, given_on: string, max_mark: int|string}  $data
     * @return array{name: string, given_on: CarbonImmutable, max_mark: int}
     */
    public static function validate(Term $term, Stream $stream, Subject $subject, AssessmentComponent $component, array $data, ?AssessmentTask $ignore = null): array
    {
        $scheme = $component->scheme;
        if ($scheme->academic_year_id !== $term->academic_year_id || $scheme->level !== $stream->schoolClass->level || $scheme->mode !== AssessmentMode::Weighted) {
            throw ValidationException::withMessages(['component' => "Choose a part of {$stream->schoolClass->level->label()}'s assessment for this year."]);
        }

        $name = preg_replace('/\s+/', ' ', trim($data['name'])) ?? '';
        if ($name === '' || mb_strlen($name) > 80) {
            throw ValidationException::withMessages(['name' => 'Enter a name of up to 80 characters.']);
        }

        $siblings = AssessmentTask::query()
            ->where(['term_id' => $term->id, 'stream_id' => $stream->id, 'subject_id' => $subject->id, 'assessment_component_id' => $component->id])
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id));

        if ((clone $siblings)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists()) {
            throw ValidationException::withMessages(['name' => "There is already a task called {$name} under {$component->name}."]);
        }
        if ($component->kind === ComponentKind::Exam && (clone $siblings)->exists()) {
            throw ValidationException::withMessages(['component' => "{$component->name} has one task per term. Edit the one that is there."]);
        }

        $date = self::date($data['given_on']);
        if ($date === null) {
            throw ValidationException::withMessages(['given_on' => 'Enter the date the work was given.']);
        }
        if ($date->lt($term->starts_on) || $date->gt($term->ends_on)) {
            throw ValidationException::withMessages(['given_on' => "The date must fall within {$term->name} ({$term->starts_on->format('j M')} – {$term->ends_on->format('j M Y')})."]);
        }

        $max = filter_var($data['max_mark'], FILTER_VALIDATE_INT);
        if ($max === false || $max < 1 || $max > self::MAX_MARK) {
            throw ValidationException::withMessages(['max_mark' => 'Use a whole number from 1 to '.self::MAX_MARK.'.']);
        }

        return ['name' => $name, 'given_on' => $date, 'max_mark' => $max];
    }

    /**
     * @return Collection<int, Stream>
     */
    private static function streams(): Collection
    {
        return Stream::query()->select('streams.*')
            ->join('school_classes', 'school_classes.id', '=', 'streams.school_class_id')
            ->with('schoolClass')
            ->orderBy('school_classes.sequence')->orderBy('streams.name')
            ->get()->toBase();
    }

    private static function date(string $value): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }
        [$y, $m, $d] = array_map(intval(...), explode('-', $value));

        return checkdate($m, $d, $y) ? CarbonImmutable::create($y, $m, $d) : null;
    }
}
