<?php

namespace App\Support\Assessment;

use App\Enums\SchoolLevel;
use App\Enums\SheetProgress;
use App\Models\Stream;
use App\Models\Subject;

/**
 * Every class and subject's mark sheet for a term, grouped by level, for the head.
 */
final readonly class ProgressOverview
{
    /**
     * @param  array<int, list<ProgressCell>>  $cellsByStream  keyed by stream id
     * @param  list<array{level: SchoolLevel, streams: list<Stream>, subjects: list<Subject>, note: string|null, commentsOnly: bool}>  $levels
     */
    public function __construct(
        public array $levels,
        public array $cellsByStream,
    ) {}

    /**
     * @return list<ProgressCell>
     */
    public function cells(): array
    {
        return array_merge(...array_values($this->cellsByStream ?: [[]]));
    }

    public function cell(int $streamId, int $subjectId): ?ProgressCell
    {
        foreach ($this->cellsByStream[$streamId] ?? [] as $cell) {
            if ($cell->subject->id === $subjectId) {
                return $cell;
            }
        }

        return null;
    }

    /**
     * @return array<string, int> progress value => count
     */
    public function counts(): array
    {
        $counts = array_fill_keys(array_map(fn (SheetProgress $p) => $p->value, SheetProgress::cases()), 0);
        foreach ($this->cells() as $cell) {
            $counts[$cell->progress->value]++;
        }

        return $counts;
    }
}
