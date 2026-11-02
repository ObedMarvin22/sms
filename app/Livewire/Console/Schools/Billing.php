<?php

namespace App\Livewire\Console\Schools;

use App\Actions\Saas\CountCensusStudents;
use App\Actions\Saas\IssueSaasInvoice;
use App\Actions\Saas\KeepSchoolOpen;
use App\Actions\Saas\RecordSaasPayment;
use App\Actions\Saas\SetSchoolPlan;
use App\Actions\Saas\VoidSaasInvoice;
use App\Enums\SaasPaymentMethod;
use App\Models\Plan;
use App\Models\SaasInvoice;
use App\Models\School;
use App\Models\Term;
use App\Models\User;
use App\Support\Saas\SaasLock;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Console: one school's plan and Orange Tree invoices (issue, record payments, void, PDF).
 */
#[Layout('layouts::console')]
#[Title('Billing')]
class Billing extends Component
{
    #[Locked]
    public int $schoolId;

    public string $planId = '';

    #[Locked]
    public ?int $payingId = null;

    /** @var array{amount: string, paid_on: string, method: string, reference: string, note: string} */
    public array $payment = ['amount' => '', 'paid_on' => '', 'method' => 'bank_transfer', 'reference' => '', 'note' => ''];

    #[Locked]
    public ?int $voidingId = null;

    public string $voidReason = '';

    public string $keepOpenUntil = '';

    public string $keepOpenReason = '';

    public function mount(School $school): void
    {
        $this->authorize('view-billing');
        $this->schoolId = $school->id;
        $this->planId = (string) ($school->plan_id ?? '');
    }

    public function school(): School
    {
        return School::with('planModel')->findOrFail($this->schoolId);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    public function canManage(): bool
    {
        return $this->user()->can('manage-billing');
    }

    /**
     * @return Collection<int, Plan>
     */
    public function plans(): Collection
    {
        return Plan::where('is_active', true)->orWhere('id', (int) $this->planId)->orderBy('name')->get();
    }

    /**
     * @return Collection<int, SaasInvoice>
     */
    #[Computed]
    public function invoices(): Collection
    {
        return SaasInvoice::with(['term', 'payments.recorder'])->withSum('payments', 'amount')
            ->where('school_id', $this->schoolId)->latest('issued_at')->latest('id')->get();
    }

    /**
     * The current term, if its census date has passed and it has no live invoice: can be issued now.
     */
    public function termToBill(): ?Term
    {
        // Platform code: the console has no current school; the term is filtered by school_id.
        $term = Term::withoutGlobalScopes()->where('school_id', $this->schoolId)->where('is_current', true)->first();

        return $term !== null && ! $term->census_on->isAfter(today())
            && ! SaasInvoice::live()->where('school_id', $this->schoolId)->where('term_id', $term->id)->exists()
            ? $term : null;
    }

    public function censusCount(Term $term): int
    {
        return app(CountCensusStudents::class)->handle($this->school(), $term->census_on);
    }

    /**
     * Open, overdue (with the date it locks) or read-only.
     */
    public function lock(): SaasLock
    {
        return SaasLock::for($this->school());
    }

    public function keepOpen(KeepSchoolOpen $keepSchoolOpen): void
    {
        $this->authorize('manage-billing');
        $this->resetErrorBag();

        try {
            $keepSchoolOpen->handle($this->school(), $this->keepOpenUntil, $this->keepOpenReason);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->keepOpenUntil = $this->keepOpenReason = '';
        Flux::toast(variant: 'success', text: __('The school stays open until that date.'));
    }

    public function stopKeepingOpen(KeepSchoolOpen $keepSchoolOpen): void
    {
        $this->authorize('manage-billing');
        $keepSchoolOpen->handle($this->school(), null);
        Flux::toast(text: __('No longer kept open.'));
    }

    public function savePlan(SetSchoolPlan $setSchoolPlan): void
    {
        $this->authorize('manage-billing');
        $this->resetErrorBag();

        try {
            $setSchoolPlan->handle($this->school(), $this->planId === '' ? null : (int) $this->planId);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        Flux::toast(variant: 'success', text: __('Plan saved.'));
    }

    public function issue(IssueSaasInvoice $issue): void
    {
        $this->authorize('manage-billing');
        $this->resetErrorBag();
        $term = $this->termToBill();
        if ($term === null) {
            return;
        }

        try {
            $invoice = $issue->handle($this->school(), $term, $this->user());
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        unset($this->invoices);
        Flux::toast(variant: 'success', text: __('Invoice :number issued for :amount.', ['number' => $invoice->number, 'amount' => $invoice->amount->format()]));
    }

    public function startPayment(int $invoiceId): void
    {
        $this->authorize('manage-billing');
        $this->resetErrorBag();
        $invoice = SaasInvoice::withSum('payments', 'amount')->where('school_id', $this->schoolId)->findOrFail($invoiceId);
        $this->payingId = $invoice->id;
        $this->payment = ['amount' => $invoice->balance()->plain(), 'paid_on' => today()->toDateString(), 'method' => SaasPaymentMethod::BankTransfer->value, 'reference' => '', 'note' => ''];
        Flux::modal('saas-payment')->show();
    }

    public function recordPayment(RecordSaasPayment $record): void
    {
        $this->authorize('manage-billing');
        $this->resetErrorBag();
        $invoice = SaasInvoice::where('school_id', $this->schoolId)->findOrFail($this->payingId);

        try {
            $record->handle($this->user(), $invoice, $this->payment);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        unset($this->invoices);
        Flux::modal('saas-payment')->close();
        Flux::toast(variant: 'success', text: __('Payment recorded.'));
    }

    public function startVoid(int $invoiceId): void
    {
        $this->authorize('manage-billing');
        $this->resetErrorBag();
        $this->voidingId = SaasInvoice::where('school_id', $this->schoolId)->findOrFail($invoiceId)->id;
        $this->voidReason = '';
        Flux::modal('saas-void')->show();
    }

    public function void(VoidSaasInvoice $voidInvoice): void
    {
        $this->authorize('manage-billing');
        $this->resetErrorBag();
        $invoice = SaasInvoice::where('school_id', $this->schoolId)->findOrFail($this->voidingId);

        try {
            $voidInvoice->handle($this->user(), $invoice, $this->voidReason);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        unset($this->invoices);
        Flux::modal('saas-void')->close();
        Flux::toast(text: __('Invoice :number voided.', ['number' => $invoice->number]));
    }

    public function render(): View
    {
        return view('livewire.console.schools.billing');
    }
}
