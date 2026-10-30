<?php

namespace App\Livewire\Fees;

use App\Actions\Fees\DeleteInstalmentPlan;
use App\Actions\Fees\SaveInstalmentPlan;
use App\Enums\PermissionKey;
use App\Models\InstalmentPlan;
use App\Models\StudentInstalmentPlan;
use App\Models\Term;
use App\Support\Money\Percent;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * A term's instalment plans. Students without a plan follow the default one (or, with none,
 * pay the whole bill when the term starts).
 */
#[Title('Instalment plans')]
class InstalmentPlans extends Component
{
    #[Url(as: 'term')]
    public string $termId = '';

    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public bool $isDefault = false;

    /** @var list<array{due_on: string, percent: string}> */
    public array $steps = [];

    public function mount(): void
    {
        $this->authorize(PermissionKey::FinanceView->value);

        if ($this->selectedTerm() === null) {
            $this->termId = (string) (Term::current()->value('id') ?? $this->terms()->first()->id ?? '');
        }
    }

    public function updatedTermId(): void
    {
        unset($this->plans);
    }

    public function canManage(): bool
    {
        return Auth::user()?->can(PermissionKey::FeesManage->value) ?? false;
    }

    /**
     * @return Collection<int, Term>
     */
    #[Computed]
    public function terms(): Collection
    {
        return Term::with('academicYear')->orderByDesc('starts_on')->get();
    }

    public function selectedTerm(): ?Term
    {
        return $this->terms()->firstWhere('id', (int) $this->termId);
    }

    /**
     * @return Collection<int, InstalmentPlan>
     */
    #[Computed]
    public function plans(): Collection
    {
        return InstalmentPlan::with('steps')->where('term_id', (int) $this->termId)
            ->orderByDesc('is_default')->orderBy('name')->get();
    }

    /**
     * Students on each plan: plan id => count.
     *
     * @return array<int, int>
     */
    #[Computed]
    public function followers(): array
    {
        return StudentInstalmentPlan::where('term_id', (int) $this->termId)->selectRaw('instalment_plan_id, count(*) as students')
            ->groupBy('instalment_plan_id')->pluck('students', 'instalment_plan_id')->map(intval(...))->all();
    }

    /**
     * "50% by 18 Sep · 50% by 30 Oct".
     */
    public function summary(InstalmentPlan $plan): string
    {
        return $plan->steps->map(fn ($step) => Percent::format($step->percent).' by '.$step->due_on->format('j M'))->implode(' · ');
    }

    public function startCreate(): void
    {
        $this->authorize(PermissionKey::FeesManage->value);
        $this->resetErrorBag();
        $this->editingId = null;
        $this->name = '';
        $this->isDefault = $this->plans()->isEmpty();
        $this->steps = [['due_on' => '', 'percent' => '50'], ['due_on' => '', 'percent' => '50']];
        Flux::modal('plan')->show();
    }

    public function startEdit(int $id): void
    {
        $this->authorize(PermissionKey::FeesManage->value);
        $this->resetErrorBag();
        $plan = InstalmentPlan::with('steps')->findOrFail($id);
        $this->editingId = $plan->id;
        $this->name = $plan->name;
        $this->isDefault = $plan->is_default;
        $this->steps = array_values($plan->steps->map(fn ($step) => [
            'due_on' => $step->due_on->toDateString(),
            'percent' => Percent::plain($step->percent),
        ])->all());
        Flux::modal('plan')->show();
    }

    public function addStep(): void
    {
        if (count($this->steps) < SaveInstalmentPlan::MAX_STEPS) {
            $this->steps[] = ['due_on' => '', 'percent' => ''];
        }
    }

    public function removeStep(int $index): void
    {
        $this->steps = array_values(array_filter($this->steps, fn (int $i) => $i !== $index, ARRAY_FILTER_USE_KEY));
    }

    public function save(SaveInstalmentPlan $saveInstalmentPlan): void
    {
        $this->authorize(PermissionKey::FeesManage->value);
        $this->resetErrorBag();
        $term = $this->selectedTerm() ?? abort(404);

        try {
            $plan = $saveInstalmentPlan->handle($term, $this->editingId === null ? null : InstalmentPlan::findOrFail($this->editingId), $this->name, $this->steps, $this->isDefault);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        unset($this->plans);
        Flux::modal('plan')->close();
        Flux::toast(variant: 'success', text: __(':name saved.', ['name' => $plan->name]));
    }

    public function delete(int $id, DeleteInstalmentPlan $deleteInstalmentPlan): void
    {
        $this->authorize(PermissionKey::FeesManage->value);

        try {
            $deleteInstalmentPlan->handle(InstalmentPlan::with('steps')->findOrFail($id));
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: (string) collect($e->errors())->flatten()->first());

            return;
        }

        unset($this->plans);
    }

    public function render(): View
    {
        return view('livewire.fees.instalment-plans');
    }
}
