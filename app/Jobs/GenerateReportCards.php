<?php

namespace App\Jobs;

use App\Enums\ReportCardStatus;
use App\Jobs\Middleware\RunInSchool;
use App\Models\ReportCard;
use App\Support\Pdf\ReportCardPdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Makes the PDFs for up to 15 report cards and stores them on the private disk. Runs from the
 * database queue (cron on shared hosting). Safe to retry: a card is simply made again.
 */
class GenerateReportCards implements SchoolJob, ShouldQueue
{
    use Queueable;

    public const BATCH = 15;

    public const DISK = 'local';

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 120;

    /**
     * @param  list<int>  $reportCardIds
     */
    public function __construct(public int $school, public array $reportCardIds) {}

    public function schoolId(): int
    {
        return $this->school;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RunInSchool];
    }

    public function handle(ReportCardPdf $pdf): void
    {
        // Cards approved or published in the meantime are not remade.
        $cards = ReportCard::whereKey($this->reportCardIds)
            ->whereIn('status', [ReportCardStatus::Draft, ReportCardStatus::Generated])->get();

        foreach ($cards as $card) {
            $path = "report-cards/{$this->school}/{$card->term_id}/{$card->id}.pdf";
            Storage::disk(self::DISK)->put($path, $pdf->render($card));

            $card->update(['pdf_path' => $path, 'generated_at' => now(), 'status' => ReportCardStatus::Generated]);
        }
    }
}
