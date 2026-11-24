<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ParentApp\FindParentChild;
use App\Enums\ReportCardStatus;
use App\Http\Resources\V1\ReportCardResource;
use App\Jobs\GenerateReportCards;
use App\Models\ReportCard;
use App\Support\Pdf\ReportCardPdf;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * A child's published report cards, newest first, and each one's PDF. Every guardian who can
 * see the child (full or limited access) may; cards not yet published do not exist here.
 */
class ChildReportCardsController extends ParentController
{
    public function index(Request $request, int $student, FindParentChild $find): AnonymousResourceCollection
    {
        $find->handle($this->guardian($request), $student);

        $cards = ReportCard::with(['term.academicYear', 'stream.schoolClass'])
            ->where('student_id', $student)->where('status', ReportCardStatus::Published)
            ->orderByDesc('published_at')->get();

        return ReportCardResource::collection($cards);
    }

    public function pdf(Request $request, int $student, int $reportCard, FindParentChild $find, ReportCardPdf $pdf): Response
    {
        $find->handle($this->guardian($request), $student);
        $card = ReportCard::where('student_id', $student)->where('status', ReportCardStatus::Published)->whereKey($reportCard)->firstOrFail();

        $disk = Storage::disk(GenerateReportCards::DISK);
        $body = $card->pdf_path !== null && $disk->exists($card->pdf_path) ? (string) $disk->get($card->pdf_path) : $pdf->render($card);

        return response($body, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.ReportCardPdf::filename($card).'"',
        ]);
    }
}
