<?php

namespace App\Actions\Assessment;

use App\Enums\AssessmentMode;
use App\Models\AssessmentSheet;
use App\Support\Assessment\ScoreSheet;
use Illuminate\Validation\ValidationException;

/**
 * Whether a class's mark sheet is locked, and what still stops it being submitted.
 * A submitted sheet refuses marks and task changes from everyone, heads included, until a
 * head unlocks it with a reason. Queries are school-scoped.
 */
final class SheetLock
{
    /** How many "what is missing" lines to show before summing up the rest. */
    public const MAX_PROBLEMS = 8;

    public static function find(int $termId, int $streamId, int $subjectId): ?AssessmentSheet
    {
        return AssessmentSheet::where(['term_id' => $termId, 'stream_id' => $streamId, 'subject_id' => $subjectId])->first();
    }

    /**
     * @throws ValidationException when the sheet is submitted
     */
    public static function ensureOpen(int $termId, int $streamId, int $subjectId, string $field = 'sheet'): void
    {
        $sheet = self::find($termId, $streamId, $subjectId);

        if ($sheet?->isLocked()) {
            $on = $sheet->submitted_at?->format('j M') ?? '';
            throw ValidationException::withMessages([$field => "These marks were submitted on {$on} and are locked. Ask the head to unlock them."]);
        }
    }

    /**
     * What is missing before the sheet can be submitted, in plain sentences. Empty when ready.
     *
     * @return list<string>
     */
    public static function problems(ScoreSheet $sheet): array
    {
        if ($sheet->scheme === null) {
            return ['This level has no assessment set up yet.'];
        }
        if ($sheet->scheme->mode === AssessmentMode::Descriptive) {
            return ['This level is assessed with comments only.'];
        }

        $problems = [];
        foreach ($sheet->scheme->components as $part) {
            if (! $sheet->tasks->contains('assessment_component_id', $part->id)) {
                $problems[] = "{$part->name} has no task yet.";
            }
        }

        if ($sheet->tasks->isNotEmpty() && $sheet->rows === []) {
            $problems[] = 'There are no students on this sheet.';
        }

        $missing = [];
        foreach ($sheet->rows as $row) {
            if (! $row->editable) {
                continue; // left the class: what they have stays as it is
            }

            $tasks = $sheet->tasks->filter(fn ($task) => ! isset($sheet->scores[ScoreSheet::key($task->id, $row->student->id)]));
            if ($tasks->isNotEmpty()) {
                $missing[] = "{$row->student->full_name} has no mark for ".$tasks->pluck('name')->implode(', ').'.';
            }
        }

        if (count($missing) > self::MAX_PROBLEMS) {
            $more = count($missing) - self::MAX_PROBLEMS;
            $missing = [...array_slice($missing, 0, self::MAX_PROBLEMS), "…and {$more} more ".($more === 1 ? 'student' : 'students').' with empty boxes.'];
        }

        return [...$problems, ...$missing];
    }
}
