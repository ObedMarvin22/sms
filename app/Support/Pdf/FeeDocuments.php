<?php

namespace App\Support\Pdf;

use App\Actions\Payments\BuildStatement;
use App\Actions\Schools\UpdateSchoolProfile;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Student;
use App\Support\Money\AmountInWords;
use App\Support\Money\Money;
use App\Support\Tenancy\CurrentSchool;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * The fee PDFs (dompdf), the same for staff and parents: a payment's receipt (A5) and a student's
 * fee statement (A4). Callers decide who may download; everything here is for the current school.
 */
final class FeeDocuments
{
    public function __construct(private CurrentSchool $currentSchool, private BuildStatement $buildStatement) {}

    /**
     * A receipt; reversed payments print with a REVERSED stamp.
     */
    public function receipt(Payment $payment): Response
    {
        $school = $this->currentSchool->require();
        $payment->loadMissing(['student.currentEnrolment.stream.schoolClass', 'recorder']);

        // Balance straight after this payment: every ledger entry up to and including its credit.
        $credit = LedgerEntry::where('payment_id', $payment->id)->orderBy('id')->firstOrFail();
        $balanceAfter = Money::pesewas((int) LedgerEntry::where('student_id', $payment->student_id)->where('id', '<=', $credit->id)->sum('amount'));

        return Pdf::loadView('pdf.receipt', [
            'school' => $school,
            'logo' => $this->logoDataUri($school->logo_path),
            'payment' => $payment,
            'words' => AmountInWords::for($payment->amount),
            'balanceAfter' => $balanceAfter,
        ])->setPaper('a5')->download('receipt-'.Str::slug(str_replace('/', '-', $payment->receipt_number)).'.pdf');
    }

    /**
     * Every ledger entry for the student, oldest first, with the balance after each, and the
     * balance today.
     */
    public function statement(Student $student): Response
    {
        $school = $this->currentSchool->require();
        $student->loadMissing('currentEnrolment.stream.schoolClass');
        $rows = $this->buildStatement->handle($student->id);

        return Pdf::loadView('pdf.statement', [
            'school' => $school,
            'logo' => $this->logoDataUri($school->logo_path),
            'student' => $student,
            'rows' => $rows,
            'balance' => LedgerEntry::balanceFor($student->id),
            'printedAt' => now(),
        ])->setPaper('a4')->download('statement-'.Str::slug(str_replace('/', '-', $student->admission_number)).'-'.now()->format('Y-m-d').'.pdf');
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
