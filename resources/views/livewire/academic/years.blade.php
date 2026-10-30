<div class="flex max-w-4xl flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Academic years') }}</flux:heading>
            <flux:text>{{ __('Years, terms and census dates. One term is current at a time.') }}</flux:text>
        </div>
        <flux:button :href="route('academic-years.create')" variant="primary" icon="plus" wire:navigate data-test="new-year-link">
            {{ __('New academic year') }}
        </flux:button>
    </div>

    <div class="max-w-xs">
        <flux:select wire:model.live="academicPeriod" :label="__('The school year is divided into')" :disabled="$this->years->isNotEmpty()" data-test="academic-period">
            @foreach ($this->periodOptions() as $option)
                <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:error name="academic_period" />
        @if ($this->years->isNotEmpty())
            <flux:text size="sm" class="mt-1">{{ __('Fixed once the first academic year exists.') }}</flux:text>
        @endif
    </div>

    @forelse ($this->years as $year)
        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" wire:key="year-{{ $year->id }}">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <flux:heading size="lg">{{ $year->name }}</flux:heading>
                    @if ($year->is_current)
                        <flux:badge color="green" size="sm">{{ __('Current') }}</flux:badge>
                    @endif
                    <flux:text size="sm">{{ $year->starts_on->format('j M Y') }} – {{ $year->ends_on->format('j M Y') }}</flux:text>
                </div>
                @unless ($year->is_current)
                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="deleteYear({{ $year->id }})"
                        wire:confirm="{{ __('Delete :year and its terms?', ['year' => $year->name]) }}">{{ __('Delete') }}</flux:button>
                @endunless
            </div>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Starts') }}</flux:table.column>
                    <flux:table.column>{{ __('Ends') }}</flux:table.column>
                    <flux:table.column>{{ __('Census') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($year->terms as $term)
                        <flux:table.row :key="'term-'.$term->id">
                            <flux:table.cell>
                                {{ $term->name }}
                                @if ($term->is_current)
                                    <flux:badge color="green" size="sm" class="ms-1" data-test="current-term">{{ __('Current') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $term->starts_on->format('j M Y') }}</flux:table.cell>
                            <flux:table.cell>{{ $term->ends_on->format('j M Y') }}</flux:table.cell>
                            <flux:table.cell>{{ $term->census_on->format('j M Y') }}</flux:table.cell>
                            <flux:table.cell class="text-end">
                                <flux:button size="sm" variant="ghost" wire:click="editTerm({{ $term->id }})">{{ __('Edit dates') }}</flux:button>
                                @unless ($term->is_current)
                                    <flux:button size="sm" wire:click="makeCurrent({{ $term->id }})" data-test="make-current-{{ $term->id }}">{{ __('Make current') }}</flux:button>
                                @endunless
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @empty
        <flux:callout icon="calendar-days">
            <flux:callout.heading>{{ __('No academic years yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Create the current academic year to get started.') }}</flux:callout.text>
        </flux:callout>
    @endforelse

    <flux:modal name="edit-term" class="md:w-96">
        <form wire:submit="saveTerm" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Edit dates') }}</flux:heading>
            <flux:input type="date" wire:model="editStartsOn" :label="__('Starts')" />
            <flux:error name="edit_starts_on" />
            <flux:input type="date" wire:model="editEndsOn" :label="__('Ends')" />
            <flux:error name="edit_ends_on" />
            <flux:input type="date" wire:model="editCensusOn" :label="__('Census date')" :description="__('Active students on this date are counted for billing.')" />
            <flux:error name="edit_census_on" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
