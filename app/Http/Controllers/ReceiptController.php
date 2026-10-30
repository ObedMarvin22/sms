<?php

namespace App\Http\Controllers;

use App\Actions\Schools\UpdateSchoolProfile;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Support\Money\AmountInWords;
use App\Support\Money\Money;
use App\Support\Tenancy\CurrentSchool;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * A payment's receipt as an A5 PDF (dompdf). The payment is bound through the school scope, so
 * another school's receipt is a 404. Reversed payments print with a REVERSED stamp.
 */
class ReceiptController extends Controller
{
    public function __invoke(CurrentSchool $currentSchool, Payment $payment): Response
    {
        $school = $currentSchool->require();
        $payment->loadMissing(['student.currentEnrolment.stream.schoolClass', 'recorder']);

        // Balance straight after this payment: every ledger entry up to and including its credit.
        $credit = LedgerEntry::where('payment_id', $payment->id)->orderBy('id')->firstOrFail();
        $balanceAfter = Money::pesewas((int) LedgerEntry::where('student_id', $payment->student_id)->where('id', '<=', $credit->id)->sum('amount'));

        $pdf = Pdf::loadView('pdf.receipt', [
            'school' => $school,
            'logo' => $this->logoDataUri($school->logo_path),
            'payment' => $payment,
            'words' => AmountInWords::for($payment->amount),
            'balanceAfter' => $balanceAfter,
        ])->setPaper('a5');

        return $pdf->download('receipt-'.Str::slug(str_replace('/', '-', $payment->receipt_number)).'.pdf');
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
