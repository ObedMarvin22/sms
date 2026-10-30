<?php

namespace App\Livewire\Fees;

use App\Actions\Fees\CopyFeeStructures;
use App\Actions\Fees\FeeRules;
use App\Actions\Fees\SaveFeeStructure;
use App\Enums\PermissionKey;
use App\Enums\Residence;
use App\Models\FeeItem;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Support\Money\Money;
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
 * Fee structures for one term: a grid of classes against Day / Boarding with each total.
 * A cell opens its lines for editing (fees.manage).
 */
#[Title('Fee structures')]
class Structures extends Component
{
    #[Url(as: 'term')]
    public string $termId = '';

    #[Locked]
    public ?int $editingClassId = null;

    #[Locked]
    public string $editingResidence = '';

    /** @var array<int, string> fee item id => amount typed in cedis */
    public array $amounts = [];

    public function mount(): void
    {
        $this->authorize(PermissionKey::FinanceView->value);

        if ($this->selectedTerm() === null) {
            $this->termId = (string) (Term::current()->value('id') ?? $this->terms()->first()->id ?? '');
        }
    }

    public function updatedTermId(): void
    {
        unset($this->structures);
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
     * @return Collection<int, SchoolClass>
     */
    #[Computed]
    public function classes(): Collection
    {
        return SchoolClass::orderBy('sequence')->get();
    }

    /**
     * @return list<Residence>
     */
    public function residences(): array
    {
        return FeeRules::offersBoarding() ? Residence::cases() : [Residence::Day];
    }

    /**
     * This term's structures keyed "classId-residence".
     *
     * @return \Illuminate\Support\Collection<string, FeeStructure>
     */
    #[Computed]
    public function structures(): \Illuminate\Support\Collection
    {
        return FeeStructure::with('lines')->where('term_id', (int) $this->termId)->get()
            ->keyBy(fn (FeeStructure $s) => $s->school_class_id.'-'.$s->residence->value);
    }

    /**
     * The term before the selected one, if it has structures to copy.
     */
    public function copySource(): ?Term
    {
        $term = $this->selectedTerm();
        if ($term === null) {
            return null;
        }

        $previous = $this->terms()->first(fn (Term $t) => $t->starts_on->lt($term->starts_on));

        return $previous !== null && FeeStructure::where('term_id', $previous->id)->exists() ? $previous : null;
    }

    public function copyFromPrevious(CopyFeeStructures $copyFeeStructures): void
    {
        $this->authorize(PermissionKey::FeesManage->value);
        $from = $this->copySource();
        $to = $this->selectedTerm();
        if ($from === null || $to === null) {
            return;
        }

        try {
            $copied = $copyFeeStructures->handle($from, $to);
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: (string) collect($e->errors())->flatten()->first());

            return;
        }

        unset($this->structures);
        Flux::toast(variant: 'success', text: $copied > 0
            ? trans_choice('{1} :count structure copied from :term.|[2,*] :count structures copied from :term.', $copied, ['count' => $copied, 'term' => $from->name])
            : __('Every class already has a structure for this term.'));
    }

    /**
     * Items the edit form shows: every active item, plus archived ones already on this structure.
     *
     * @return Collection<int, FeeItem>
     */
    public function editItems(): Collection
    {
        return FeeItem::query()
            ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', array_keys(array_filter($this->amounts, fn (string $a) => $a !== ''))))
            ->orderBy('is_optional')->orderBy('name')->get();
    }

    public function editingClass(): ?SchoolClass
    {
        return $this->classes()->firstWhere('id', $this->editingClassId);
    }

    public function startEdit(int $classId, string $residence): void
    {
        $this->authorize(PermissionKey::FeesManage->value);
        $this->resetErrorBag();
        $this->editingClassId = SchoolClass::findOrFail($classId)->id;
        $this->editingResidence = Residence::from($residence)->value;

        $lines = $this->structures()->get("{$classId}-{$residence}")?->lines->keyBy('fee_item_id') ?? collect();
        $this->amounts = [];
        foreach ($lines as $itemId => $line) {
            $this->amounts[(int) $itemId] = $line->amount->plain();
        }
        foreach (FeeItem::where('is_active', true)->pluck('id') as $itemId) {
            $this->amounts[(int) $itemId] ??= '';
        }

        Flux::modal('structure')->show();
    }

    public function save(SaveFeeStructure $saveFeeStructure): void
    {
        $this->authorize(PermissionKey::FeesManage->value);
        $this->resetErrorBag();
        $term = $this->selectedTerm() ?? abort(404);
        $class = SchoolClass::findOrFail($this->editingClassId);

        try {
            $saveFeeStructure->handle($term, $class, Residence::from($this->editingResidence), $this->amounts);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        unset($this->structures);
        Flux::modal('structure')->close();
        Flux::toast(variant: 'success', text: __(':class (:residence) saved for :term.', [
            'class' => $class->name, 'residence' => Residence::from($this->editingResidence)->label(), 'term' => $term->name,
        ]));
    }

    /**
     * @return list<int>
     */
    #[Computed]
    public function optionalItemIds(): array
    {
        return array_values(array_map(intval(...), FeeItem::where('is_optional', true)->pluck('id')->all()));
    }

    /**
     * A structure's total split into what everyone pays and what only students who opt in pay.
     *
     * @return array{everyone: Money, optional: Money}
     */
    public function totals(FeeStructure $structure): array
    {
        [$optional, $everyone] = $structure->lines->partition(fn (FeeStructureItem $line) => in_array($line->fee_item_id, $this->optionalItemIds(), true));

        return [
            'everyone' => Money::sum($everyone->map(fn (FeeStructureItem $line) => $line->amount)),
            'optional' => Money::sum($optional->map(fn (FeeStructureItem $line) => $line->amount)),
        ];
    }

    public function render(): View
    {
        return view('livewire.fees.structures');
    }
}
