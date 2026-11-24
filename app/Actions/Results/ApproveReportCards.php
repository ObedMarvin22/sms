<?php

namespace App\Actions\Results;

use App\Enums\ReportCardStatus;
use App\Models\Stream;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The head approves a class's report cards once every one is made (no drafts). Logged.
 */
class ApproveReportCards
{
    /**
     * @return int How many cards were approved.
     */
    public function handle(User $by, Term $term, Stream $stream): int
    {
        ReportCardRules::authorizeHead($by);
        $cards = ReportCardRules::cards($term, $stream);

        if ($cards->isEmpty()) {
            throw ValidationException::withMessages(['report_cards' => "{$stream->full_name} has no report cards yet."]);
        }
        $notMade = $cards->where('status', ReportCardStatus::Draft)->count();
        if ($notMade > 0) {
            throw ValidationException::withMessages(['report_cards' => trans_choice(
                '{1} 1 report card is not made yet. Make them all before approving.|[2,*] :count report cards are not made yet. Make them all before approving.',
                $notMade, ['count' => $notMade],
            )]);
        }

        return DB::transaction(function () use ($by, $cards): int {
            $approved = 0;
            foreach ($cards->where('status', ReportCardStatus::Generated) as $card) {
                $card->update(['status' => ReportCardStatus::Approved, 'approved_by' => $by->id, 'approved_at' => now()]);
                $approved++;
            }

            return $approved;
        });
    }
}
