<?php

namespace App\Livewire\Fees;

use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\ReversePayment;
use App\Enums\PaymentMethod;
use App\Enums\PaystackTransactionStatus;
use App\Enums\PermissionKey;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\PaystackTransaction;
use App\Models\Student;
use App\Models\User;
use App\Support\Money\Money;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Payments received: a searchable list, recording a payment (payments.record) and reversing one
 * recorded by mistake (fees.manage).
 */
#[Title('Payments')]
class Payments extends Component
{
    use WithFileUploads;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url(as: 'method')]
    public string $methodFilter = '';

    #[Url(as: 'from')]
    public string $from = '';

    #[Url(as: 'to')]
    public string $to = '';

    /** Student to record a payment for (from ?student= on a student's page, or the search). */
    #[Url(as: 'student')]
    public string $studentId = '';

    public string $studentSearch = '';

    /** @var array{amount: string, method: string, reference: string, paid_on: string, note: string} */
    public array $form = ['amount' => '', 'method' => 'cash', 'reference' => '', 'paid_on' => '', 'note' => ''];

    /** @var TemporaryUploadedFile|null */
    public $proof = null;

    #[Locked]
    public ?int $reversingId = null;

    public string $reason = '';

    public function mount(): void
    {
        $this->authorize('view-payments');

        if ($this->studentId !== '' && $this->canRecord() && Student::whereKey((int) $this->studentId)->exists()) {
            $this->startRecord((int) $this->studentId);
        }
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'methodFilter', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function canRecord(): bool
    {
        return Auth::user()?->can(PermissionKey::PaymentsRecord->value) ?? false;
    }

    public function canReverse(): bool
    {
        return Auth::user()?->can(PermissionKey::FeesManage->value) ?? false;
    }

    /**
     * @return LengthAwarePaginator<int, Payment>
     */
    #[Computed]
    public function payments(): LengthAwarePaginator
    {
        $term = trim($this->search);
        $method = PaymentMethod::tryFrom($this->methodFilter);

        return Payment::with(['student', 'recorder'])
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('receipt_number', 'like', "%{$term}%")
                ->orWhere('reference', 'like', '%'.mb_strtoupper($term).'%')
                ->orWhereHas('student', fn ($q) => $q->withTrashed()->where(fn ($q) => $q
                    ->where('admission_number', 'like', "%{$term}%")
                    ->orWhere('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")))))
            ->when($method !== null, fn ($q) => $q->where('method', $method->value))
            ->when(preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->from) === 1, fn ($q) => $q->where('paid_on', '>=', $this->from))
            ->when(preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->to) === 1, fn ($q) => $q->where('paid_on', '<=', $this->to))
            ->latest('id')
            ->paginate(25);
    }

    /**
     * Online payments Paystack and our records disagree on (ReconcilePaystack, SettlePaystackTransaction).
     *
     * @return Collection<int, PaystackTransaction>
     */
    public function attention(): Collection
    {
        return PaystackTransaction::with('student')->where('status', PaystackTransactionStatus::Attention->value)->latest('id')->get();
    }

    /**
     * @return list<PaymentMethod>
     */
    public function methods(): array
    {
        return PaymentMethod::cases();
    }

    /**
     * Students matching the search in the record form (name or admission number).
     *
     * @return Collection<int, Student>
     */
    public function studentMatches(): Collection
    {
        $term = trim($this->studentSearch);
        if (mb_strlen($term) < 2) {
            return new Collection;
        }

        return Student::with('currentEnrolment.stream.schoolClass')
            ->where(fn ($q) => $q->where('admission_number', 'like', "%{$term}%")
                ->orWhere('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%"))
            ->orderBy('last_name')->orderBy('first_name')->limit(8)->get();
    }

    public function selectedStudent(): ?Student
    {
        return $this->studentId === '' ? null : Student::with('currentEnrolment.stream.schoolClass')->find((int) $this->studentId);
    }

    public function selectedBalance(): ?Money
    {
        $student = $this->selectedStudent();

        return $student === null ? null : LedgerEntry::balanceFor($student->id);
    }

    public function selectedMethod(): PaymentMethod
    {
        return PaymentMethod::tryFrom($this->form['method']) ?? PaymentMethod::Cash;
    }

    /**
     * Warning when the typed amount is more than the student owes.
     */
    public function overpayment(): ?string
    {
        $balance = $this->selectedBalance();
        try {
            $amount = Money::parse($this->form['amount']);
        } catch (\InvalidArgumentException) {
            return null;
        }

        if ($balance === null || $amount->pesewas <= max(0, $balance->pesewas)) {
            return null;
        }

        return __('This is more than the :owed owed. The extra :extra will be a credit on the next bill.', [
            'owed' => Money::pesewas(max(0, $balance->pesewas))->format(),
            'extra' => Money::pesewas($amount->pesewas - max(0, $balance->pesewas))->format(),
        ]);
    }

    public function startRecord(?int $studentId = null): void
    {
        $this->authorize(PermissionKey::PaymentsRecord->value);
        $this->resetErrorBag();
        $this->studentId = $studentId === null ? '' : (string) $studentId;
        $this->studentSearch = '';
        $this->form = ['amount' => '', 'method' => PaymentMethod::Cash->value, 'reference' => '', 'paid_on' => now()->toDateString(), 'note' => ''];
        $this->proof = null;
        Flux::modal('record-payment')->show();
    }

    public function pickStudent(int $id): void
    {
        $this->studentId = (string) Student::findOrFail($id)->id;
        $this->studentSearch = '';
    }

    public function record(RecordPayment $recordPayment): void
    {
        $this->authorize(PermissionKey::PaymentsRecord->value);
        $this->resetErrorBag();
        $student = $this->selectedStudent();
        if ($student === null) {
            $this->addError('student', __('Choose the student the payment is for.'));

            return;
        }

        try {
            $payment = $recordPayment->handle($this->user(), $student, $this->form, $this->proof instanceof TemporaryUploadedFile ? $this->proof : null);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        unset($this->payments);
        $this->studentId = '';
        $this->proof = null;
        Flux::modal('record-payment')->close();
        Flux::toast(variant: 'success', text: __('Receipt :number: :amount for :name.', [
            'number' => $payment->receipt_number, 'amount' => $payment->amount->format(), 'name' => $student->full_name,
        ]));
    }

    public function startReverse(int $id): void
    {
        $this->authorize(PermissionKey::FeesManage->value);
        $this->resetErrorBag();
        $this->reversingId = Payment::findOrFail($id)->id;
        $this->reason = '';
        Flux::modal('reverse-payment')->show();
    }

    public function reversing(): ?Payment
    {
        return $this->reversingId === null ? null : Payment::with('student')->find($this->reversingId);
    }

    public function reverse(ReversePayment $reversePayment): void
    {
        $this->authorize(PermissionKey::FeesManage->value);
        $this->resetErrorBag();

        try {
            $payment = $reversePayment->handle($this->user(), Payment::findOrFail($this->reversingId), $this->reason);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        unset($this->payments);
        Flux::modal('reverse-payment')->close();
        Flux::toast(variant: 'success', text: __('Receipt :number reversed.', ['number' => $payment->receipt_number]));
    }

    public function render(): View
    {
        return view('livewire.fees.payments');
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
