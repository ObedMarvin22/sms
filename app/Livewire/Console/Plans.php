<?php

namespace App\Livewire\Console;

use App\Actions\Saas\SavePlan;
use App\Models\Plan;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Console: Orange Tree's price plans (rate per active student per term).
 */
#[Layout('layouts::console')]
#[Title('Plans')]
class Plans extends Component
{
    public ?int $editingId = null;

    /** @var array{name: string, rate: string, description: string, is_active: bool} */
    public array $form = ['name' => '', 'rate' => '', 'description' => '', 'is_active' => true];

    public function mount(): void
    {
        $this->authorize('view-billing');
    }

    public function canManage(): bool
    {
        return Auth::user()?->can('manage-billing') ?? false;
    }

    /**
     * @return Collection<int, Plan>
     */
    #[Computed]
    public function plans(): Collection
    {
        return Plan::withCount('schools')->orderBy('name')->get();
    }

    public function startCreate(): void
    {
        $this->authorize('manage-billing');
        $this->resetErrorBag();
        $this->editingId = null;
        $this->form = ['name' => '', 'rate' => '', 'description' => '', 'is_active' => true];
        Flux::modal('plan')->show();
    }

    public function startEdit(int $id): void
    {
        $this->authorize('manage-billing');
        $this->resetErrorBag();
        $plan = Plan::findOrFail($id);
        $this->editingId = $plan->id;
        $this->form = ['name' => $plan->name, 'rate' => $plan->rate_per_student->plain(), 'description' => (string) $plan->description, 'is_active' => $plan->is_active];
        Flux::modal('plan')->show();
    }

    public function save(SavePlan $savePlan): void
    {
        $this->authorize('manage-billing');
        $this->resetErrorBag();

        try {
            $plan = $savePlan->handle($this->editingId === null ? null : Plan::findOrFail($this->editingId), $this->form);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        unset($this->plans);
        Flux::modal('plan')->close();
        Flux::toast(variant: 'success', text: __(':name saved.', ['name' => $plan->name]));
    }

    public function render(): View
    {
        return view('livewire.console.plans');
    }
}
