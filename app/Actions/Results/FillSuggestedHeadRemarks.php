<?php

namespace App\Actions\Results;

use App\Enums\PermissionKey;
use App\Enums\ReportCardStatus;
use App\Models\ReportCard;
use App\Models\Stream;
use App\Models\Term;
use App\Models\User;
use App\Support\Results\HeadRemarks;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Gives every student in a class who has no head's remark yet the suggested one for their
 * average, for the head to read and change. Remarks already written are never replaced.
 */
class FillSuggestedHeadRemarks
{
    public function __construct(private CompileClassResults $compile) {}

    /**
     * @return int How many remarks were filled in.
     */
    public function handle(User $by, Term $term, Stream $stream): int
    {
        if (! $by->can(PermissionKey::GradesApprove->value)) {
            throw new AuthorizationException('Only the head can write the head\'s remark.');
        }

        // Make sure every student in the class has a report card to write on.
        $this->compile->handle($term, $stream);

        $filled = 0;
        $cards = ReportCard::where(['term_id' => $term->id, 'stream_id' => $stream->id])
            ->whereNull('head_remark')
            ->whereNotIn('status', [ReportCardStatus::Approved, ReportCardStatus::Published])
            ->get();
        foreach ($cards as $card) {
            $card->update(['head_remark' => HeadRemarks::suggest($card->average === null ? null : (float) $card->average)]);
            $filled++;
        }

        return $filled;
    }
}
