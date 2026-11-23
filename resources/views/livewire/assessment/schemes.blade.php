@php
    // Continuous-assessment parts get stepped shades of one hue; the exam is the dark block.
    $caShades = ['bg-sky-700', 'bg-sky-500', 'bg-sky-300', 'bg-sky-200', 'bg-cyan-600', 'bg-cyan-400', 'bg-cyan-200', 'bg-sky-100'];
    $shade = function (\App\Models\AssessmentComponent $part, int $i) use ($caShades): string {
        return $part->kind->isContinuous() ? $caShades[$i % count($caShades)] : 'bg-zinc-800 dark:bg-zinc-200';
    };
@endphp

<div class="flex max-w-4xl flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Assessment') }}</flux:heading>
            <flux:text>{{ __('How each level is marked: the parts of continuous assessment, the exam, and their weights.') }}</flux:text>
        </div>
        @if ($this->years->isNotEmpty())
            <div class="w-full sm:w-56">
                <flux:select wire:model.live="yearId" :label="__('Academic year')" data-test="year">
                    @foreach ($this->years as $y)
                        <flux:select.option :value="$y->id">{{ $y->name }}{{ $y->is_current ? ' ('.__('current').')' : '' }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endif
    </div>

    @if ($this->year === null)
        <flux:callout icon="calendar-days">
            <flux:callout.heading>{{ __('Create an academic year first') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Assessment is set per academic year, so the school needs one before levels can be set up.') }}</flux:callout.text>
            <x-slot name="actions">
                <flux:button :href="route('academic-years.create')" wire:navigate size="sm">{{ __('New academic year') }}</flux:button>
            </x-slot>
        </flux:callout>
    @else
        @if ($this->schemes->isEmpty())
            <div class="rounded-xl border border-dashed border-zinc-300 p-6 dark:border-zinc-600" data-test="empty">
                <flux:heading size="lg">{{ __('No assessment set up for :year', ['year' => $this->year->name]) }}</flux:heading>
                <flux:text class="mt-1 max-w-prose">
                    {{ __('The standard setup gives Creche, Nursery and KG teacher comments only, and every other level 30% continuous assessment (class exercises, tests and a project) with a 70% end of term exam. You can change any level afterwards.') }}
                </flux:text>
                <div class="mt-4 flex flex-wrap gap-2">
                    <flux:button wire:click="setUpDefaults" variant="primary" data-test="set-up-defaults">{{ __('Use the standard setup') }}</flux:button>
                    @if ($this->copySource)
                        <flux:button wire:click="copyFromPrevious" data-test="copy-previous">{{ __('Copy from :year', ['year' => $this->copySource->name]) }}</flux:button>
                    @endif
                </div>
            </div>
        @endif

        <div class="divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @foreach ($this->levels as $level)
                @php($scheme = $this->schemes->get($level->value))
                <div class="flex flex-col gap-3 p-4 sm:p-5" wire:key="level-{{ $level->value }}" data-test="level-{{ $level->value }}">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <flux:heading size="lg">{{ $level->label() }}</flux:heading>
                            @if ($scheme)
                                <flux:badge size="sm" :color="$scheme->mode === \App\Enums\AssessmentMode::Weighted ? 'sky' : 'zinc'">{{ $scheme->mode->label() }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="amber">{{ __('Not set up') }}</flux:badge>
                            @endif
                        </div>
                        <flux:button size="sm" variant="ghost" :icon="$scheme ? 'pencil' : 'plus'" wire:click="edit('{{ $level->value }}')" data-test="edit-{{ $level->value }}">
                            {{ $scheme ? __('Edit') : __('Set up') }}
                        </flux:button>
                    </div>

                    @if ($scheme?->mode === \App\Enums\AssessmentMode::Weighted)
                        @php($ca = $scheme->components->filter(fn ($c) => $c->kind->isContinuous())->sum('weight'))
                        <div class="flex h-3 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800" role="img"
                            aria-label="{{ __('Continuous assessment :ca%, exam :exam%', ['ca' => $ca, 'exam' => 100 - $ca]) }}">
                            @foreach ($scheme->components as $i => $part)
                                <div class="{{ $shade($part, $i) }} h-full border-e-2 border-white last:border-e-0 dark:border-zinc-900" style="width: {{ $part->weight }}%"></div>
                            @endforeach
                        </div>
                        <ul class="grid grid-cols-1 gap-x-6 gap-y-1.5 sm:grid-cols-2">
                            @foreach ($scheme->components as $i => $part)
                                <li class="flex items-center gap-2 text-sm">
                                    <span class="{{ $shade($part, $i) }} size-2.5 shrink-0 rounded-sm" aria-hidden="true"></span>
                                    <span class="min-w-0 truncate text-zinc-700 dark:text-zinc-300">{{ $part->name }}</span>
                                    <span class="ms-auto font-medium tabular-nums text-zinc-900 dark:text-white">{{ $part->weight }}%</span>
                                </li>
                            @endforeach
                        </ul>
                        <flux:text size="sm">{{ __('Continuous assessment :ca%, exam :exam%', ['ca' => $ca, 'exam' => 100 - $ca]) }}</flux:text>
                    @elseif ($scheme)
                        <flux:text size="sm">{{ __('Teachers write a comment on each child instead of giving marks.') }}</flux:text>
                    @else
                        <flux:text size="sm">{{ __('Report cards for this level need a scheme first.') }}</flux:text>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <flux:modal name="scheme" class="w-full md:w-[46rem]">
        <form wire:submit="save" class="flex flex-col gap-5">
            <div>
                <flux:heading size="lg">
                    {{ __(':level assessment', ['level' => $editingLevel ? \App\Enums\SchoolLevel::from($editingLevel)->label() : '']) }}
                </flux:heading>
                <flux:text>{{ $this->year?->name }}</flux:text>
            </div>

            <flux:radio.group wire:model.live="mode" variant="segmented" :label="__('How is this level assessed?')" size="sm">
                @foreach (\App\Enums\AssessmentMode::cases() as $option)
                    <flux:radio :value="$option->value" :label="$option->label()" />
                @endforeach
            </flux:radio.group>

            @if ($mode === \App\Enums\AssessmentMode::Weighted->value)
                <div class="flex flex-col gap-3">
                    <div class="hidden grid-cols-[1fr_12rem_7.5rem_2rem] gap-2 sm:grid">
                        <flux:label>{{ __('Part') }}</flux:label>
                        <flux:label>{{ __('Kind') }}</flux:label>
                        <flux:label>{{ __('Weight') }}</flux:label>
                    </div>
                    @foreach ($parts as $i => $part)
                        <div class="grid grid-cols-[1fr_7.5rem_2rem] gap-2 sm:grid-cols-[1fr_12rem_7.5rem_2rem]" wire:key="part-{{ $i }}-{{ $part['id'] ?? 'new' }}">
                            <div class="col-span-3 sm:col-span-1">
                                <flux:input wire:model="parts.{{ $i }}.name" :aria-label="__('Part name')" placeholder="{{ __('e.g. Class tests') }}" />
                                <flux:error name="parts.{{ $i }}.name" />
                            </div>
                            <flux:select wire:model="parts.{{ $i }}.kind" :aria-label="__('Kind')">
                                @foreach (\App\Enums\ComponentKind::cases() as $kind)
                                    <flux:select.option :value="$kind->value">{{ $kind->label() }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <div>
                                <flux:input.group>
                                    <flux:input wire:model.live.debounce.400ms="parts.{{ $i }}.weight" type="number" min="1" max="100" inputmode="numeric" :aria-label="__('Weight')" />
                                    <flux:input.group.suffix>%</flux:input.group.suffix>
                                </flux:input.group>
                                <flux:error name="parts.{{ $i }}.weight" />
                            </div>
                            <flux:button variant="ghost" size="sm" icon="x-mark" wire:click="removePart({{ $i }})" :aria-label="__('Remove part')" class="self-start mt-1" />
                        </div>
                    @endforeach

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                        @if (count($parts) < \App\Actions\Assessment\SaveAssessmentScheme::MAX_PARTS)
                            <flux:button size="sm" variant="ghost" icon="plus" wire:click="addPart" data-test="add-part">{{ __('Add a part') }}</flux:button>
                        @else
                            <span></span>
                        @endif
                        @php($total = $this->total())
                        <div @class(['text-sm font-medium tabular-nums', 'text-green-700 dark:text-green-400' => $total === 100, 'text-red-700 dark:text-red-400' => $total !== 100]) data-test="total" aria-live="polite">
                            {{ $total === 100 ? __('Total 100%') : __('Total :total% (needs 100%)', ['total' => $total]) }}
                        </div>
                    </div>
                    <flux:error name="parts" />
                </div>
            @else
                <flux:callout icon="chat-bubble-left-ellipsis" variant="secondary">
                    <flux:callout.text>{{ __('No marks or weights. Each term, teachers write a comment on every child for the report card.') }}</flux:callout.text>
                </flux:callout>
            @endif
            <flux:error name="level" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="save-scheme">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
