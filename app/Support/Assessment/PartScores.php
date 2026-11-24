<?php

namespace App\Support\Assessment;

use App\Enums\ScoreStatus;
use App\Models\AssessmentComponent;
use App\Models\AssessmentScore;
use App\Models\AssessmentTask;

/**
 * The scoring rule (Obed, 2026-11-24): inside a part, marks add up. A part's score is
 * marks scored / marks possible × the part's weight. Absent counts as zero (its marks are
 * possible but not scored); Exempt and not-yet-entered tasks are left out for that student.
 * A part with nothing counted yet has no score, and the total adds up the parts that have one.
 */
final class PartScores
{
    /**
     * @param  iterable<AssessmentComponent>  $components
     * @param  iterable<AssessmentTask>  $tasks
     * @param  array<int, AssessmentScore>  $scores  this student's scores, keyed by task id
     * @return array{parts: array<int, float|null>, total: float|null}
     */
    public static function for(iterable $components, iterable $tasks, array $scores): array
    {
        // Work in tenths of a mark so halves add up exactly.
        $earned = [];
        $possible = [];

        foreach ($tasks as $task) {
            $score = $scores[$task->id] ?? null;
            if ($score === null || $score->status === ScoreStatus::Exempt) {
                continue;
            }

            $part = $task->assessment_component_id;
            $possible[$part] = ($possible[$part] ?? 0) + $task->max_mark * 10;
            $earned[$part] = ($earned[$part] ?? 0) + ($score->status === ScoreStatus::Marked ? (int) round((float) $score->mark * 10) : 0);
        }

        $parts = [];
        foreach ($components as $component) {
            $parts[$component->id] = isset($possible[$component->id]) && $possible[$component->id] > 0
                ? round($earned[$component->id] / $possible[$component->id] * $component->weight, 2)
                : null;
        }

        $counted = array_filter($parts, fn (?float $p) => $p !== null);

        return ['parts' => $parts, 'total' => $counted === [] ? null : round(array_sum($counted), 2)];
    }
}
