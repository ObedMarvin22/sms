<?php

namespace App\Http\Controllers;

use App\Actions\Schools\UpdateSchoolProfile;
use App\Actions\Students\BuildClassList;
use App\Models\AcademicYear;
use App\Models\Stream;
use App\Models\Term;
use App\Support\Tenancy\CurrentSchool;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * A printable A4 class list (dompdf). The stream is bound through the school scope, so another
 * school's stream id is a 404.
 */
class ClassListPdfController extends Controller
{
    public function __invoke(CurrentSchool $currentSchool, BuildClassList $buildClassList, Stream $stream): Response
    {
        $school = $currentSchool->require();
        $today = now()->toImmutable();

        $pdf = Pdf::loadView('pdf.class-list', [
            'school' => $school,
            'logo' => $this->logoDataUri($school->logo_path),
            'stream' => $stream->loadMissing('schoolClass'),
            'year' => AcademicYear::current()->first(),
            'term' => Term::current()->first(),
            'printedOn' => $today,
            'list' => $buildClassList->handle($stream, $today),
        ])->setPaper('a4');

        return $pdf->download(Str::slug($stream->full_name).'-class-list-'.$today->toDateString().'.pdf');
    }

    /**
     * The logo is on the private disk and dompdf may not fetch files, so it is embedded.
     */
    private function logoDataUri(?string $path): ?string
    {
        $disk = Storage::disk(UpdateSchoolProfile::LOGO_DISK);
        if ($path === null || ! $disk->exists($path)) {
            return null;
        }

        return 'data:'.($disk->mimeType($path) ?: 'image/png').';base64,'.base64_encode((string) $disk->get($path));
    }
}
