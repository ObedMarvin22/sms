<?php

namespace App\Actions\Results;

use App\Enums\ReportCardStatus;
use App\Models\Enrolment;
use App\Models\ReportCard;
use App\Models\Stream;
use App\Models\Term;
use App\Models\TermResult;
use App\Support\Academic\Aggregates;
use App\Support\Academic\Ranking;
use Illuminate\Support\Facades\DB;

/**
 * Works out each student's overall result for the term from their subject results: the average
 * of their subject totals, the JHS/SHS aggregate, and their position in the class by average.
 * Writes (or refreshes) a draft report card per student still in the class. Run again whenever
 * a sheet is submitted; report cards already approved or published are left alone.
 */
class CompileClassResults
{
    /**
     * @return int How many report cards were written.
     */
    public function handle(Term $term, Stream $stream): int
    {
        $stream->loadMissing('schoolClass');
        $studentIds = Enrolment::where(['academic_year_id' => $term->academic_year_id, 'stream_id' => $stream->id])
            ->where('starts_on', '<=', $term->ends_on->toDateString())
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>', $term->ends_on->toDateString()))
            ->pluck('student_id')->unique()->values()->all();

        $results = TermResult::with('subject')->where(['term_id' => $term->id, 'stream_id' => $stream->id])->get()->groupBy('student_id');

        $averages = [];
        $aggregates = [];
        $counts = [];
        foreach ($studentIds as $id) {
            $mine = $results->get($id, collect());
            $totals = $mine->pluck('total')->filter(fn ($t) => $t !== null)->map(fn ($t) => (float) $t);
            $averages[$id] = $totals->isEmpty() ? null : round($totals->avg(), 2);
            $counts[$id] = $mine->count();
            $aggregates[$id] = Aggregates::for(
                $stream->schoolClass->level,
                $mine->mapWithKeys(fn (TermResult $r) => [$r->subject->name => $r->points])->all(),
            );
        }
        $positions = Ranking::positions($averages);

        return DB::transaction(function () use ($term, $stream, $studentIds, $averages, $aggregates, $counts, $positions): int {
            $written = 0;
            foreach ($studentIds as $id) {
                $card = ReportCard::firstOrNew(['term_id' => $term->id, 'student_id' => $id]);
                if (in_array($card->status, [ReportCardStatus::Approved, ReportCardStatus::Published], true)) {
                    continue;
                }

                $card->fill([
                    'stream_id' => $stream->id,
                    'status' => ReportCardStatus::Draft,
                    'average' => $averages[$id] === null ? null : number_format($averages[$id], 2, '.', ''),
                    'aggregate' => $aggregates[$id],
                    'position' => $positions[$id] ?? null,
                    'class_size' => count($studentIds),
                    'subjects_count' => $counts[$id],
                ])->save();
                $written++;
            }

            return $written;
        });
    }
}
