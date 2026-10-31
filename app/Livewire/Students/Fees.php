<?php

namespace App\Livewire\Students;

use App\Actions\Fees\FeeRules;
use App\Actions\Fees\RemoveScholarship;
use App\Actions\Fees\SaveScholarship;
use App\Actions\Fees\SetFeeOption;
use App\Actions\Fees\SetInstalmentPlan;
use App\Actions\Fees\SetResidence;
use App\Actions\Fees\SetStudentDiscount;
use App\Actions\Payments\AdjustBalance;
use App\Actions\Payments\BuildStatement;
use App\Actions\Paystack\InitialisePaystackPayment;
use App\Enums\DiscountKind;
use App\Enums\DiscountType;
use App\Enums\PermissionKey;
use App\Enums\Residence;
use App\Models\Bill;
use App\Models\Discount;
use App\Models\FeeItem;
use App\Models\InstalmentPlan;
use App\Models\LedgerEntry;
use App\Models\Student;
use App\Models\StudentDiscount;
use App\Models\StudentInstalmentPlan;
use App\Models\Term;
use App\Models\User;
use App\Support\Money\Money;
use App\Support\Tenancy\CurrentSchool;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The fees section of a student's page: day or boarding, and the optional items they take.
 * Shown to finance.view; changed by fees.manage.
 */
class Fees extends Component
{
    #[Locked]
    public int $studentId;

    public string $residence = '';

    public string $linkAmount = '';

    public string $paymentLink = '';

    public string $linkTotal = '';

    /** @var array{direction: string, amount: string, reason: string} */
    public array $adjust = ['direction' => 'charge', 'amount' => '', 'reason' => ''];

    /** This term's instalment plan ('' = the term's default). */
    public string $planId = '';

    #[Locked]
    public ?int $editingScholarshipId = null;

    /** @var array{name: string, sponsor: string, type: string, value: string, whole_bill: bool, fee_item_ids: list<string>, ends_term_id: string} */
    public array $form = ['name' => '', 'sponsor' => '', 'type' => 'percent', 'value' => '', 'whole_bill' => true, 'fee_item_ids' => [], 'ends_term_id' => ''];

    public function mount(int $studentId): void
    {
        $this->studentId = $studentId;
        $student = $this->student();
        $this->residence = $student->residence->value;
        $this->planId = (string) StudentInstalmentPlan::where('student_id', $student->id)
            ->where('term_id', $this->currentTerm()->id ?? 0)->value('instalment_plan_id');
    }

    public function canView(): bool
    {
        return Auth::user()?->can(PermissionKey::FinanceView->value) ?? false;
    }

    public function canManage(): bool
    {
        return Auth::user()?->can(PermissionKey::FeesManage->value) ?? false;
    }

    public function offersBoarding(): bool
    {
        return FeeRules::offersBoarding();
    }

    public function student(): Student
    {
        return Student::findOrFail($this->studentId);
    }

    /**
     * Optional items students can take, plus any archived one this student still has.
     *
     * @return Collection<int, FeeItem>
     */
    public function optionalItems(): Collection
    {
        return FeeItem::where('is_optional', true)
            ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $this->takenIds()))
            ->orderBy('name')->get();
    }

    /**
     * @return list<int>
     */
    public function takenIds(): array
    {
        return array_values(array_map(intval(...), $this->student()->feeOptions()->pluck('fee_item_id')->all()));
    }

    public function updatedResidence(SetResidence $setResidence): void
    {
        $this->authorize(PermissionKey::FeesManage->value);
        $this->resetErrorBag();
        $student = $this->student();

        try {
            $setResidence->handle($student, Residence::from($this->residence));
        } catch (ValidationException $e) {
            $this->residence = $student->residence->value;
            $this->setErrorBag($e->validator->errors());

            return;
        }

        Flux::toast(variant: 'success', text: __(':name is now a :residence student.', [
            'name' => $student->first_name, 'residence' => mb_strtolower($student->residence->label()),
        ]));
    }

    public function toggleOption(int $itemId, SetFeeOption $setFeeOption): void
    {
        $this->authorize(PermissionKey::FeesManage->value);
        $this->resetErrorBag();

        try {
            $setFeeOption->handle($this->student(), FeeItem::findOrFail($itemId), ! in_array($itemId, $this->takenIds(), true));
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());
        }
    }

    /**
     * Discounts given by hand that the student has or could be given.
     *
     * @return Collection<int, Discount>
     */
    public function discounts(): Collection
    {
        $given = $this->givenDiscountIds();

        return Discount::where('kind', '!=', DiscountKind::Sibling->value)
            ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $given))
            ->orderBy('name')->get();
    }

    /**
     * @return list<int>
     */
    public function givenDiscountIds(): array
    {
        return array_values(array_map(intval(...), StudentDiscount::where('student_id', $this->studentId)->whereNotNull('discount_id')->pluck('discount_id')->all()));
    }

    public function toggleDiscount(int $discountId, SetStudentDiscount $setStudentDiscount): void
    {
        $this->authorize(PermissionKey::FeesManage->value);
        $this->resetErrorBag();

        try {
            $setStudentDiscount->handle($this->student(), Discount::findOrFail($discountId), ! in_array($discountId, $this->givenDiscountIds(), true));
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());
        }
    }

    /**
     * @return Collection<int, StudentDiscount>
     */
    public function scholarships(): Collection
    {
        return StudentDiscount::with('endsTerm')->where('student_id', $this->studentId)->whereNull('discount_id')->orderBy('name')->get();
    }

    /**
     * @return array<int, string> fee item id => name
     */
    public function itemNames(): array
    {
        return FeeItem::orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return list<DiscountType>
     */
    public function types(): array
    {
        return DiscountType::cases();
    }

    /**
     * @return Collection<int, Term>
     */
    public function terms(): Collection
    {
        return Term::with('academicYear')->orderBy('starts_on')->get();
    }

    public function startScholarship(?int $id = null): void
    {
        $this->authorize(PermissionKey::FeesManage->value);
        $this->resetErrorBag();
        $scholarship = $id === null ? null : StudentDiscount::where('student_id', $this->studentId)->whereNull('discount_id')->findOrFail($id);

        $this->editingScholarshipId = $scholarship?->id;
        $this->form = [
            'name' => (string) $scholarship?->name,
            'sponsor' => (string) $scholarship?->sponsor,
            'type' => $scholarship->type->value ?? DiscountType::Percent->value,
            'value' => $scholarship?->type?->plain((int) $scholarship->value) ?? '',
            'whole_bill' => $scholarship === null || $scholarship->fee_item_ids === null,
            'fee_item_ids' => array_map(strval(...), $scholarship->fee_item_ids ?? []),
            'ends_term_id' => (string) $scholarship?->ends_term_id,
        ];
        Flux::modal('scholarship')->show();
    }

    public function saveScholarship(SaveScholarship $saveScholarship): void
    {
        $this->authorize(PermissionKey::FeesManage->value);
        $this->resetErrorBag();
        $existing = $this->editingScholarshipId === null ? null : StudentDiscount::findOrFail($this->editingScholarshipId);

        try {
            $saveScholarship->handle($this->student(), $existing, $this->form);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        Flux::modal('scholarship')->close();
        Flux::toast(variant: 'success', text: __('Scholarship saved.'));
    }

    public function removeScholarship(int $id, RemoveScholarship $removeScholarship): void
    {
        $this->authorize(PermissionKey::FeesManage->value);
        $removeScholarship->handle(StudentDiscount::where('student_id', $this->studentId)->whereNull('discount_id')->findOrFail($id));
    }

    public function currentTerm(): ?Term
    {
        return Term::current()->first();
    }

    /**
     * @return Collection<int, InstalmentPlan>
     */
    public function plans(): Collection
    {
        return InstalmentPlan::where('term_id', $this->currentTerm()->id ?? 0)->orderByDesc('is_default')->orderBy('name')->get();
    }

    public function updatedPlanId(SetInstalmentPlan $setInstalmentPlan): void
    {
        $this->authorize(PermissionKey::FeesManage->value);
        $this->resetErrorBag();
        $term = $this->currentTerm() ?? abort(404);

        try {
            $setInstalmentPlan->handle($this->student(), $term, $this->planId === '' ? null : InstalmentPlan::findOrFail((int) $this->planId));
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        Flux::toast(variant: 'success', text: __('Instalment plan saved.'));
    }

    /**
     * Newest first.
     *
     * @return Collection<int, Bill>
     */
    public function bills(): Collection
    {
        return Bill::with(['term.academicYear', 'schoolClass', 'lines', 'instalments'])
            ->where('student_id', $this->studentId)->latest('issued_at')->latest('id')->get();
    }

    /**
     * Every ledger entry, oldest first, with the balance after it.
     *
     * @return list<array{entry: LedgerEntry, balance: Money}>
     */
    public function statement(): array
    {
        return app(BuildStatement::class)->handle($this->studentId);
    }

    public function takesOnlinePayments(): bool
    {
        return app(CurrentSchool::class)->require()->paystack_subaccount_code !== null;
    }

    /**
     * Staff make a Paystack payment link for a parent (e.g. to send by WhatsApp).
     */
    public function createPaymentLink(InitialisePaystackPayment $initialise): void
    {
        $this->authorize(PermissionKey::PaymentsRecord->value);
        $this->resetErrorBag();
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        try {
            $result = $initialise->handle($this->student(), $this->linkAmount, route('paystack.return'), initiatedBy: $user);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->paymentLink = $result['authorization_url'];
        $this->linkTotal = $result['transaction']->total->format();
    }

    public function canRecordPayments(): bool
    {
        return Auth::user()?->can(PermissionKey::PaymentsRecord->value) ?? false;
    }

    public function startAdjust(): void
    {
        $this->authorize(PermissionKey::FeesManage->value);
        $this->resetErrorBag();
        $this->adjust = ['direction' => 'charge', 'amount' => '', 'reason' => ''];
        Flux::modal('adjust-balance')->show();
    }

    public function saveAdjustment(AdjustBalance $adjustBalance): void
    {
        $this->authorize(PermissionKey::FeesManage->value);
        $this->resetErrorBag();
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        try {
            $adjustBalance->handle($user, $this->student(), $this->adjust['direction'], $this->adjust['amount'], $this->adjust['reason']);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        Flux::modal('adjust-balance')->close();
        Flux::toast(variant: 'success', text: __('Balance adjusted.'));
    }

    /**
     * What the student owes now, from the ledger (negative: in credit).
     */
    public function balance(): Money
    {
        return LedgerEntry::balanceFor($this->studentId);
    }

    public function render(): View
    {
        return view('livewire.students.fees');
    }
}
