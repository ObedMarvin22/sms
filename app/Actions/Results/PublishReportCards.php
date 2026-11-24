<?php

namespace App\Actions\Results;

use App\Enums\GuardianAccess;
use App\Enums\ReportCardStatus;
use App\Models\GuardianStudent;
use App\Models\Stream;
use App\Models\Term;
use App\Models\User;
use App\Notifications\ReportCardPublished;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The head publishes a class's approved report cards: only then can parents see and download
 * them. Each child's guardians who can see them in the parent app (full or limited access) get
 * a notice. Logged.
 */
class PublishReportCards
{
    /**
     * @return int How many cards were published.
     */
    public function handle(User $by, Term $term, Stream $stream): int
    {
        ReportCardRules::authorizeHead($by);
        $cards = ReportCardRules::cards($term, $stream)->where('status', ReportCardStatus::Approved);

        if ($cards->isEmpty()) {
            throw ValidationException::withMessages(['report_cards' => "Approve {$stream->full_name}'s report cards before publishing them."]);
        }

        DB::transaction(function () use ($cards): void {
            foreach ($cards as $card) {
                $card->update(['status' => ReportCardStatus::Published, 'published_at' => now()]);
            }
        });

        // Notices go to the guardian (as all parent-app notices do), so a parent who signs up
        // later still finds them.
        $links = GuardianStudent::with('guardian')->whereIn('student_id', $cards->pluck('student_id'))
            ->where('access_level', '!=', GuardianAccess::None->value)->get()->groupBy('student_id');
        foreach ($cards as $card) {
            foreach ($links->get($card->student_id, collect()) as $link) {
                $link->guardian->notify(new ReportCardPublished($card));
            }
        }

        return $cards->count();
    }
}
