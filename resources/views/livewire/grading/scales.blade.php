<div class="flex max-w-4xl flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Grading') }}</flux:heading>
        <flux:text>{{ __('How each level\'s term total (out of 100) becomes a grade on the report card. Totals are rounded to a whole number first, so 79.5 counts as 80.') }}</flux:text>
    </div>

    @php($graded = collect($this->levels)->filter(fn ($l) => \App\Support\Academic\DefaultGradeScales::for($l) !== null))
    @if ($graded->contains(fn ($l) => ! $this->scales->has($l->value)))
        <div class="rounded-xl border border-dashed border-zinc-300 p-5 dark:border-zinc-600" data-test="defaults">
            <flux:heading>{{ __('Some levels have no grade scale yet') }}</flux:heading>
            <flux:text class="mt-1 max-w-prose">{{ __('The standard scales are NaCCA proficiency levels for Primary, grades 1 to 9 for JHS (BECE style) and A1 to F9 for SHS (WASSCE style). You can change any of them.') }}</flux:text>
            <flux:button wire:click="setUpDefaults" variant="primary" size="sm" class="mt-3" data-test="set-up-scales">{{ __('Use the standard scales') }}</flux:button>
        </div>
    @endif

    <div class="flex flex-col gap-4">
        @foreach ($this->levels as $level)
            @php($scale = $this->scales->get($level->value))
            @php($graded = \App\Support\Academic\DefaultGradeScales::for($level) !== null)
            <section class="rounded-xl border border-zinc-200 dark:border-zinc-700" wire:key="scale-{{ $level->value }}" data-test="scale-{{ $level->value }}">
                <header class="flex items-center justify-between gap-3 px-4 py-3 sm:px-5">
                    <div class="flex min-w-0 flex-col sm:flex-row sm:items-baseline sm:gap-2">
                        <flux:heading size="lg">{{ $level->label() }}</flux:heading>
                        <flux:text size="sm" class="truncate">
                            {{ $scale?->name ?? ($graded ? __('No scale yet') : __('Comments only, no grades')) }}
                        </flux:text>
                    </div>
                    @if ($graded || $scale)
                        <flux:button size="sm" variant="ghost" :icon="$scale ? 'pencil' : 'plus'" wire:click="edit('{{ $level->value }}')" data-test="edit-{{ $level->value }}">
                            {{ $scale ? __('Edit') : __('Set up') }}
                        </flux:button>
                    @endif
                </header>

                @if ($scale)
                    <div class="overflow-x-auto border-t border-zinc-200 dark:border-zinc-700">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-zinc-500 dark:text-zinc-400">
                                    <th class="px-4 py-2 font-medium sm:px-5">{{ __('Total') }}</th>
                                    <th class="px-4 py-2 font-medium">{{ __('Grade') }}</th>
                                    <th class="px-4 py-2 font-medium">{{ __('Meaning') }}</th>
                                    @if ($scale->bands->contains(fn ($b) => $b->points !== null))
                                        <th class="px-4 py-2 text-right font-medium sm:px-5">{{ __('Points') }}</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach ($scale->bands as $i => $band)
                                    @php($top = $i === 0 ? 100 : $scale->bands[$i - 1]->min_score - 1)
                                    <tr>
                                        <td class="px-4 py-1.5 tabular-nums text-zinc-600 sm:px-5 dark:text-zinc-300">{{ $band->min_score }}–{{ $top }}</td>
                                        <td class="px-4 py-1.5 font-semibold text-zinc-900 dark:text-white">{{ $band->grade }}</td>
                                        <td class="px-4 py-1.5 text-zinc-700 dark:text-zinc-300">{{ $band->label }}</td>
                                        @if ($scale->bands->contains(fn ($b) => $b->points !== null))
                                            <td class="px-4 py-1.5 text-right tabular-nums sm:px-5">{{ $band->points ?? '–' }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endforeach
    </div>

    <flux:modal name="scale" class="w-full md:w-[42rem]">
        <form wire:submit="save" class="flex flex-col gap-5">
            <flux:heading size="lg">{{ __(':level grades', ['level' => $editingLevel ? \App\Enums\SchoolLevel::from($editingLevel)->label() : '']) }}</flux:heading>
            <flux:input wire:model="name" :label="__('Name of the scale')" />

            <div class="flex flex-col gap-2">
                <div class="hidden grid-cols-[6rem_5rem_1fr_5rem_2rem] gap-2 sm:grid">
                    <flux:label>{{ __('From') }}</flux:label>
                    <flux:label>{{ __('Grade') }}</flux:label>
                    <flux:label>{{ __('Meaning') }}</flux:label>
                    <flux:label>{{ __('Points') }}</flux:label>
                </div>
                @foreach ($bands as $i => $band)
                    <div class="grid grid-cols-[5rem_4.5rem_1fr_2rem] gap-2 sm:grid-cols-[6rem_5rem_1fr_5rem_2rem]" wire:key="band-{{ $i }}">
                        <div>
                            <flux:input wire:model="bands.{{ $i }}.min_score" type="number" min="0" max="100" inputmode="numeric" :aria-label="__('From total')" />
                            <flux:error name="bands.{{ $i }}.min_score" />
                        </div>
                        <div>
                            <flux:input wire:model="bands.{{ $i }}.grade" maxlength="4" :aria-label="__('Grade')" />
                            <flux:error name="bands.{{ $i }}.grade" />
                        </div>
                        <div class="col-span-1">
                            <flux:input wire:model="bands.{{ $i }}.label" :aria-label="__('Meaning')" />
                            <flux:error name="bands.{{ $i }}.label" />
                        </div>
                        <div class="col-span-3 sm:col-span-1">
                            <flux:input wire:model="bands.{{ $i }}.points" type="number" min="1" max="99" inputmode="numeric" :aria-label="__('Points')" :placeholder="__('Points')" />
                            <flux:error name="bands.{{ $i }}.points" />
                        </div>
                        <flux:button variant="ghost" size="sm" icon="x-mark" wire:click="removeBand({{ $i }})" :aria-label="__('Remove band')" class="mt-1 self-start" />
                    </div>
                @endforeach
                <flux:error name="bands" />
                @if (count($bands) < \App\Actions\Grading\SaveGradeScale::MAX_BANDS)
                    <flux:button size="sm" variant="ghost" icon="plus" wire:click="addBand" class="self-start">{{ __('Add a band') }}</flux:button>
                @endif
                <flux:text size="sm">{{ __('Each band runs from its number up to the next band. One band must start at 0. Points are what JHS and SHS aggregates add up (1 is best).') }}</flux:text>
            </div>
            <flux:error name="level" />
            <flux:error name="name" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="save-scale">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
