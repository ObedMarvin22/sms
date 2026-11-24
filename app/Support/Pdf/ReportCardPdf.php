<?php

namespace App\Support\Pdf;

use App\Models\ReportCard;
use App\Support\Results\ReportCardData;
use App\Support\Tenancy\CurrentSchool;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/**
 * A student's report card as an A4 PDF (dompdf), laid out for their level: early years (comments
 * per learning area), primary (class score, exam, total, grade) or JHS/SHS (with grade points and
 * the aggregate). The school's logo and colour head every card.
 */
final class ReportCardPdf
{
    public function __construct(private CurrentSchool $currentSchool) {}

    public function render(ReportCard $card): string
    {
        $data = ReportCardData::for($this->currentSchool->require(), $card);

        return Pdf::loadView('pdf.report-cards.'.$data->template, ['d' => $data])->setPaper('a4')->output();
    }

    public static function filename(ReportCard $card): string
    {
        $card->loadMissing(['student', 'term']);

        return 'report-'.Str::slug($card->student->full_name.' '.$card->term->name).'.pdf';
    }
}
