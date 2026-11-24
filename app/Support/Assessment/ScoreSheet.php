<?php

namespace App\Support\Assessment;

use App\Models\AssessmentScheme;
use App\Models\AssessmentScore;
use App\Models\AssessmentTask;
use Illuminate\Database\Eloquent\Collection;

/**
 * Everything a mark sheet shows for one class, subject and term.
 */
final readonly class ScoreSheet
{
    /**
     * @param  Collection<int, AssessmentTask>  $tasks  in part order, then date
     * @param  list<SheetRow>  $rows
     * @param  array<string, AssessmentScore>  $scores  keyed by self::key()
     * @param  array<int, array{parts: array<int, float|null>, total: float|null}>  $results  keyed by student id
     */
    public function __construct(
        public ?AssessmentScheme $scheme,
        public Collection $tasks,
        public array $rows,
        public array $scores,
        public array $results,
    ) {}

    public static function key(int $taskId, int $studentId): string
    {
        return "t{$taskId}-s{$studentId}";
    }
}
