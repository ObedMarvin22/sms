<?php

namespace App\Livewire\Fees;

use App\Actions\Billing\PreviewTermBilling;
use App\Actions\Billing\StartTermBilling;
use App\Enums\BillingRunStatus;
use App\Enums\PermissionKey;
use App\Enums\Residence;
use App\Models\AcademicYear;
use App\Models\BillingRun;
use App\Models\Term;
use App\Models\User;
use App\Support\Money\Money;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Bills a term's students: a preview (who, how much, what is missing), then a queued run whose
 * progress refreshes every few seconds.
 */
#[Title('Billing')]
class Billing extends Component
{
    #[Url(as: 'term')]
    public string $termId = '';

    public function mount(): void
    {
        $this->authorize(PermissionKey::FinanceView->value);

        if ($this->selectedTerm() === null) {
            $this->termId = (string) (Term::current()->value('id') ?? $this->terms()->first()->id ?? '');
        }
    }

    public function canManage(): bool
    {
        return Auth::user()?->can(PermissionKey::FeesManage->value) ?? false;
    }

    /**
     * The current academic year's terms (only those can be billed).
     *
     * @return Collection<int, Term>
     */
    #[Computed]
    public function terms(): Collection
    {
        return Term::where('academic_year_id', AcademicYear::current()->value('id'))->orderBy('starts_on')->get();
    }

    public function selectedTerm(): ?Term
    {
        return $this->terms()->firstWhere('id', (int) $this->termId);
    }

    /**
     * @return array{groups: list<array{class: string, residence: Residence, students: int, has_structure: bool, estimate: Money}>, to_bill: int, already_billed: int, missing_structures: int, estimate: Money}|null
     */
    public function preview(): ?array
    {
        $term = $this->selectedTerm();

        return $term === null ? null : app(PreviewTermBilling::class)->handle($term);
    }

    /**
     * @return Collection<int, BillingRun>
     */
    public function runs(): Collection
    {
        return BillingRun::with('user')->where('term_id', (int) $this->termId)->latest('id')->get();
    }

    public function running(): bool
    {
        return BillingRun::where('term_id', (int) $this->termId)->where('status', BillingRunStatus::Queued->value)->exists();
    }

    public function start(StartTermBilling $startTermBilling): void
    {
        $this->authorize(PermissionKey::FeesManage->value);
        $user = Auth::user();
        $term = $this->selectedTerm();
        abort_unless($user instanceof User && $term !== null, 403);

        try {
            $run = $startTermBilling->handle($user, $term);
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: (string) collect($e->errors())->flatten()->first());

            return;
        }

        Flux::toast(variant: 'success', text: trans_choice(
            '{1} Billing :count student. This page updates as bills are issued.|[2,*] Billing :count students. This page updates as bills are issued.',
            $run->total_students, ['count' => $run->total_students],
        ));
    }

    public function render(): View
    {
        return view('livewire.fees.billing');
    }
}
