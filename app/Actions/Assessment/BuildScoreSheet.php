<?php

namespace App\Actions\Assessment;

use App\Models\AssessmentScore;
use App\Models\AssessmentTask;
use App\Models\Stream;
use App\Models\Subject;
use App\Models\Term;
use App\Support\Assessment\PartScores;
use App\Support\Assessment\ScoreSheet;

/**
 * Builds a class's mark sheet for a subject and term: the students, the tasks, the marks
 * entered and each student's part scores and total so far. Four queries, whatever the size.
 */
class BuildScoreSheet
{
    public function handle(Term $term, Stream $stream, Subject $subject): ScoreSheet
    {
        $scheme = TaskRules::schemeFor($stream, $term->academic_year_id);
        $order = $scheme?->components->pluck('sequence', 'id')->all() ?? [];

        $tasks = AssessmentTask::where(['term_id' => $term->id, 'stream_id' => $stream->id, 'subject_id' => $subject->id])
            ->orderBy('given_on')->orderBy('id')->get()
            ->sortBy(fn (AssessmentTask $t) => $order[$t->assessment_component_id] ?? PHP_INT_MAX)->values();

        $rows = ScoreRules::roster($term, $stream, $subject);

        $scores = [];
        $byStudent = [];
        if ($tasks->isNotEmpty()) {
            foreach (AssessmentScore::whereIn('assessment_task_id', $tasks->modelKeys())->get() as $score) {
                $scores[ScoreSheet::key($score->assessment_task_id, $score->student_id)] = $score;
                $byStudent[$score->student_id][$score->assessment_task_id] = $score;
            }
        }

        $results = [];
        foreach ($rows as $row) {
            $results[$row->student->id] = PartScores::for($scheme->components ?? [], $tasks, $byStudent[$row->student->id] ?? []);
        }

        return new ScoreSheet($scheme, $tasks, $rows, $scores, $results);
    }
}
