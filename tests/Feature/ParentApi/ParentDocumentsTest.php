<?php

require_once __DIR__.'/helpers.php';

use App\Actions\Billing\IssueBill;
use App\Actions\Fees\SaveFeeStructure;
use App\Actions\Payments\BuildStatement;
use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\ReversePayment;
use App\Enums\GuardianAccess;
use App\Enums\Residence;
use App\Enums\RoleKey;
use App\Models\LedgerEntry;
use App\Models\School;
use App\Models\Student;
use App\Support\Tenancy\CurrentSchool;

/*
| Parents download the fee statement and receipts as PDFs, the same documents staff print.
| Full access only; a receipt must belong to the parent's own child.
*/

beforeEach(function () {
    setUpParentSchool();
    app(SaveFeeStructure::class)->handle($this->term1, $this->p1, Residence::Day, [$this->tuition->id => '1000']);
    $this->bursar = staffWith(RoleKey::Bursar);

    $this->ama = feeStudent('Ama');   // full access
    $this->kofi = feeStudent('Kofi'); // limited
    $this->yaw = feeStudent('Yaw');   // another family's
    guardianWithChild(child: $this->ama);
    guardianWithChild(access: GuardianAccess::Limited, child: $this->kofi);
    guardianWithChild('+233244000222', child: $this->yaw);

    foreach ([$this->ama, $this->kofi, $this->yaw] as $child) {
        app(IssueBill::class)->handle($child, $this->term1);
    }
    $pay = fn (Student $student, string $amount) => app(RecordPayment::class)->handle($this->bursar, $student, ['amount' => $amount, 'method' => 'cash', 'paid_on' => '2026-10-28']);
    $this->amaPayment = $pay($this->ama, '400');
    $this->kofiPayment = $pay($this->kofi, '100');
    $this->yawPayment = $pay($this->yaw, '250');

    $this->withToken(activateParent());
});

function assertPdf($response, string $namePart): void
{
    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toContain($namePart)
        ->and(substr((string) $response->getContent(), 0, 5))->toBe('%PDF-');
}

describe('the parent app', function () {
    it('downloads the fee statement', function () {
        assertPdf($this->get(api("children/{$this->ama->id}/statement.pdf")), 'statement-');
    });

    it("downloads a receipt for the child's own payment, also a reversed one", function () {
        assertPdf($this->get(api("children/{$this->ama->id}/receipts/{$this->amaPayment->id}.pdf")), 'receipt-');

        app(ReversePayment::class)->handle($this->bursar, $this->amaPayment, 'Cheque bounced');
        assertPdf($this->get(api("children/{$this->ama->id}/receipts/{$this->amaPayment->id}.pdf")), 'receipt-');
    });

    it("refuses another child's receipt, even through the parent's own child", function () {
        $this->getJson(api("children/{$this->ama->id}/receipts/{$this->yawPayment->id}.pdf"))->assertNotFound();
        $this->getJson(api("children/{$this->ama->id}/receipts/{$this->kofiPayment->id}.pdf"))->assertNotFound();
        $this->getJson(api("children/{$this->yaw->id}/receipts/{$this->yawPayment->id}.pdf"))->assertNotFound();
        $this->getJson(api("children/{$this->yaw->id}/statement.pdf"))->assertNotFound();
    });

    it('refuses documents with limited access', function () {
        $this->getJson(api("children/{$this->kofi->id}/statement.pdf"))->assertForbidden();
        $this->getJson(api("children/{$this->kofi->id}/receipts/{$this->kofiPayment->id}.pdf"))->assertForbidden();
    });

    it('needs a parent token', function () {
        $this->withToken('')->getJson(api("children/{$this->ama->id}/statement.pdf"))->assertUnauthorized();
    });

    it('lists the payment id on statement lines, so the app can offer the receipt', function () {
        $lines = $this->getJson(api("children/{$this->ama->id}/statement"))->json('data');

        expect(array_column($lines, 'payment_id'))->toBe([null, $this->amaPayment->id]);
    });
});

describe('the statement', function () {
    it('shows every line, the balance after each, and the balance to pay', function () {
        $html = view('pdf.statement', [
            'school' => $this->school, 'logo' => null, 'student' => $this->ama->load('currentEnrolment.stream.schoolClass'),
            'rows' => app(BuildStatement::class)->handle($this->ama->id),
            'balance' => LedgerEntry::balanceFor($this->ama->id), 'printedAt' => now(),
        ])->render();

        expect($html)->toContain('FEE STATEMENT')
            ->toContain($this->ama->admission_number)
            ->toContain($this->amaPayment->receipt_number)
            ->toContain('GH₵1,000.00')  // charged
            ->toContain('GH₵400.00')    // paid
            ->toContain('Balance to pay')
            ->toContain('GH₵600.00');
    });

    it('says when the student is in credit', function () {
        app(RecordPayment::class)->handle($this->bursar, $this->ama, ['amount' => '700', 'method' => 'cash', 'paid_on' => '2026-10-28']);

        $html = view('pdf.statement', [
            'school' => $this->school, 'logo' => null, 'student' => $this->ama, 'rows' => app(BuildStatement::class)->handle($this->ama->id),
            'balance' => LedgerEntry::balanceFor($this->ama->id), 'printedAt' => now(),
        ])->render();

        expect($html)->toContain('In credit')->toContain('GH₵100.00');
    });
});

describe('staff', function () {
    it('download the statement from the student page', function () {
        assertPdf($this->actingAs($this->bursar)->get("http://greenhill.localhost/students/{$this->ama->id}/statement/pdf"), 'statement-');
    });

    it('need permission to see payments', function () {
        $this->actingAs(staffWith(RoleKey::GateStaff))
            ->get("http://greenhill.localhost/students/{$this->ama->id}/statement/pdf")->assertForbidden();
    });

    it("cannot download another school's statement", function () {
        $hillview = School::factory()->active()->create(['subdomain' => 'hillview']);
        $theirs = app(CurrentSchool::class)->runFor($hillview, fn () => Student::factory()->create(['school_id' => $hillview->id]));

        $this->actingAs($this->bursar)->get("http://greenhill.localhost/students/{$theirs->id}/statement/pdf")->assertNotFound();
    });
});
