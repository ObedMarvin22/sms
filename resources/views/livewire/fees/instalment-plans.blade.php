<div class="flex max-w-4xl flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Instalment plans') }}</flux:heading>
            <flux:text>{{ __('How a term\'s fees may be paid in parts. Students follow the default plan unless given another on their page.') }}</flux:text>
        </div>
        @if ($this->canManage() && $this->selectedTerm())
            <flux:button wire:click="startCreate" variant="primary" icon="plus" data-test="new-plan">{{ __('New plan') }}</flux:button>
        @endif
    </div>

    @if ($this->terms->isEmpty())
        <flux:text>{{ __('Add an academic year first.') }}</flux:text>
    @else
        <flux:select wire:model.live="termId" :label="__('Term')" class="max-w-72">
            @foreach ($this->terms as $term)
                <flux:select.option :value="$term->id">{{ $term->academicYear->name }} · {{ $term->name }}</flux:select.option>
            @endforeach
        </flux:select>

        @if ($this->plans->isEmpty())
            <flux:callout icon="calendar">
                <flux:callout.text>{{ __('No plans for this term: every bill is due in full on the first day of term (:date).', ['date' => $this->selectedTerm()?->starts_on->format('j M Y')]) }}</flux:callout.text>
            </flux:callout>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Plan') }}</flux:table.column>
                    <flux:table.column>{{ __('Instalments') }}</flux:table.column>
                    <flux:table.column>{{ __('Students') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->plans as $plan)
                        <flux:table.row :key="'plan-'.$plan->id" data-test="plan-row">
                            <flux:table.cell>
                                {{ $plan->name }}
                                @if ($plan->is_default)
                                    <flux:badge size="sm" color="green">{{ __('Default') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $this->summary($plan) }}</flux:table.cell>
                            <flux:table.cell>{{ $plan->is_default ? __('Everyone else') : ($this->followers[$plan->id] ?? 0) }}</flux:table.cell>
                            <flux:table.cell class="text-end">
                                @if ($this->canManage())
                                    <flux:button size="sm" variant="ghost" icon="pencil" wire:click="startEdit({{ $plan->id }})" />
                                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $plan->id }})"
                                        wire:confirm="{{ __('Delete :plan?', ['plan' => $plan->name]) }}" />
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    @endif

    @if ($this->canManage())
    <flux:modal name="plan" class="md:w-[32rem]">
        <form wire:submit="save" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $editingId ? __('Edit plan') : __('New plan') }}</flux:heading>
            <flux:input wire:model="name" :label="__('Name')" :placeholder="__('e.g. Two instalments')" />
            <flux:error name="name" />
            <flux:checkbox wire:model="isDefault" :label="__('Default for this term')" :description="__('Students not given another plan follow this one.')" />

            <div class="flex flex-col gap-2">
                <flux:text size="sm">{{ __('Instalments (shares must add up to 100%)') }}</flux:text>
                @foreach ($steps as $i => $step)
                    <div class="grid grid-cols-[1fr_7rem_auto] items-start gap-2" wire:key="step-{{ $i }}">
                        <div>
                            <flux:input type="date" wire:model="steps.{{ $i }}.due_on" :aria-label="__('Due by')" />
                            <flux:error name="steps.{{ $i }}.due_on" />
                        </div>
                        <div>
                            <flux:input wire:model="steps.{{ $i }}.percent" inputmode="decimal" :aria-label="__('Share (%)')" placeholder="%" />
                            <flux:error name="steps.{{ $i }}.percent" />
                        </div>
                        <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="removeStep({{ $i }})" :aria-label="__('Remove')" />
                    </div>
                @endforeach
                <flux:error name="steps" />
                @if (count($steps) < \App\Actions\Fees\SaveInstalmentPlan::MAX_STEPS)
                    <div><flux:button size="sm" icon="plus" wire:click="addStep">{{ __('Add instalment') }}</flux:button></div>
                @endif
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
    @endif
</div>
