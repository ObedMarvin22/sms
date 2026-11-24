<?php

namespace App\Http\Controllers;

use App\Actions\Assessment\TaskRules;
use App\Enums\PermissionKey;
use App\Models\ReportCard;
use App\Support\Pdf\ReportCardPdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff preview of a student's report card as a PDF, shown in the browser. Heads see any; a
 * class teacher sees their own class's. (Parents download published ones from the parent app.)
 */
class ReportCardPdfController extends Controller
{
    public function __invoke(ReportCard $reportCard, ReportCardPdf $pdf): Response
    {
        $user = auth()->user();
        $reportCard->loadMissing('term');
        abort_unless(
            $user !== null && ($user->can(PermissionKey::GradesApprove->value)
                || in_array($reportCard->stream_id, TaskRules::classTeacherStreamIds($user, $reportCard->term->academic_year_id), true)),
            403,
        );

        return response($pdf->render($reportCard), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.ReportCardPdf::filename($reportCard).'"',
        ]);
    }
}
