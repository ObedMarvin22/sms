<?php

namespace App\Actions\Results;

use App\Actions\Assessment\BuildScoreSheet;
use App\Models\GradeScale;
use App\Models\Stream;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TermResult;
use App\Support\Academic\Ranking;
use Illuminate\Support\Facades\DB;

/**
 * Writes each student's result for a submitted mark sheet: the term total (the sum of part
 * scores), the grade from the level's scale and their position in the subject. A snapshot,
 * replaced on each submit. Students who left during the term get no result.
 */
class RecordSheetResults
{
    public function __construct(private BuildScoreSheet $build) {}

    /**
     * @return int How many results were written.
     */
    public function handle(Term $term, Stream $stream, Subject $subject): int
    {
        $sheet = $this->build->handle($term, $stream, $subject);
        if ($sheet->scheme === null) {
            return 0;
        }

        $scale = GradeScale::with('bands')->where('level', $stream->schoolClass->level)->first();
        $rows = array_values(array_filter($sheet->rows, fn ($row) => $row->editable));
        $totals = [];
        foreach ($rows as $row) {
            $totals[$row->student->id] = $sheet->results[$row->student->id]['total'];
        }
        $positions = Ranking::positions($totals);

        return DB::transaction(function () use ($term, $stream, $subject, $sheet, $scale, $rows, $totals, $positions): int {
            self::clear($term, $stream, $subject);

            foreach ($rows as $row) {
                $id = $row->student->id;
                $total = $totals[$id];
                $band = $total === null ? null : $scale?->bandFor($total);

                TermResult::create([
                    'term_id' => $term->id,
                    'stream_id' => $stream->id,
                    'subject_id' => $subject->id,
                    'student_id' => $id,
                    'total' => $total === null ? null : number_format($total, 2, '.', ''),
                    'grade' => $band?->grade,
                    'grade_label' => $band?->label,
                    'points' => $band?->points,
                    'subject_position' => $positions[$id] ?? null,
                    'parts' => array_values($sheet->scheme->components->map(fn ($part) => [
                        'name' => $part->name,
                        'kind' => $part->kind->value,
                        'weight' => $part->weight,
                        'score' => $sheet->results[$id]['parts'][$part->id] ?? null,
                    ])->all()),
                ]);
            }

            return count($rows);
        });
    }

    /**
     * Removes a sheet's results (when a head unlocks it, until it is submitted again).
     */
    public static function clear(Term $term, Stream $stream, Subject $subject): void
    {
        TermResult::where(['term_id' => $term->id, 'stream_id' => $stream->id, 'subject_id' => $subject->id])->delete();
    }
}
