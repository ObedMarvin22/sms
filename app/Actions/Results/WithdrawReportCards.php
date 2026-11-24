<?php

namespace App\Actions\Results;

use App\Enums\ReportCardStatus;
use App\Models\Stream;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The head takes back a class's approved or published report cards (parents lose access) so
 * marks or remarks can be corrected and the cards made again. Logged.
 */
class WithdrawReportCards
{
    /**
     * @return int How many cards were withdrawn.
     */
    public function handle(User $by, Term $term, Stream $stream): int
    {
        ReportCardRules::authorizeHead($by);

        return DB::transaction(function () use ($term, $stream): int {
            $withdrawn = 0;
            foreach (ReportCardRules::cards($term, $stream)->whereIn('status', [ReportCardStatus::Approved, ReportCardStatus::Published]) as $card) {
                $card->update(['status' => ReportCardStatus::Generated, 'approved_by' => null, 'approved_at' => null, 'published_at' => null]);
                $withdrawn++;
            }

            return $withdrawn;
        });
    }
}
