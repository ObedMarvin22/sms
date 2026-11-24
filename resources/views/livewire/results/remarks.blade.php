@php
    $attendance = $this->attendance();
    $fmt = fn ($v) => $v === null ? null : rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
    $field = 'block w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none transition-colors focus:border-zinc-500 focus:ring-2 focus:ring-zinc-500/20 disabled:border-transparent disabled:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white dark:disabled:bg-zinc-800/50';
@endphp

<div class="flex max-w-5xl flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Remarks') }}</flux:heading>
            <flux:text>{{ __('What the report card says about each child: the class teacher\'s remark, conduct and interest, and the head\'s remark. Each box saves when you leave it.') }}</flux:text>
        </div>
        @if ($this->streams->count() > 1)
            <div class="w-full sm:w-56">
                <flux:select wire:model.live="streamId" :label="__('Class')">
                    @foreach ($this->streams as $s)
                        <flux:select.option :value="$s->id">{{ $s->full_name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endif
    </div>

    @if ($this->term === null || $this->selectedStream === null)
        <flux:callout icon="user-group">
            <flux:callout.heading>{{ __('No class to show') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Remarks are written by the class teacher of each class, and by the head.') }}</flux:callout.text>
        </flux:callout>
    @elseif ($this->cards->isEmpty())
        <flux:callout icon="user-group">
            <flux:callout.heading>{{ __('No children in :class this term', ['class' => $this->selectedStream->full_name]) }}</flux:callout.heading>
        </flux:callout>
    @else
        @if ($this->isHead)
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <flux:text size="sm">{{ __('Short of time? Fill each empty head\'s remark with a suggestion based on the child\'s average, then read and change them.') }}</flux:text>
                <flux:button size="sm" icon="sparkles" wire:click="fillSuggested" data-test="fill-suggested">{{ __('Suggest head\'s remarks') }}</flux:button>
            </div>
        @endif

        @if ($errors->any())
            <flux:callout variant="danger" icon="exclamation-triangle">
                <flux:callout.text>
                    <ul class="list-inside list-disc">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
                </flux:callout.text>
            </flux:callout>
        @endif

        <ol class="flex flex-col gap-3">
            @foreach ($this->cards as $card)
                @php($key = 's'.$card->student_id)
                @php($days = $attendance[$card->student_id] ?? ['present' => 0, 'total' => 0])
                @php($locked = in_array($card->status, [\App\Enums\ReportCardStatus::Approved, \App\Enums\ReportCardStatus::Published], true))
                <li class="rounded-xl border border-zinc-200 p-4 sm:p-5 dark:border-zinc-700" wire:key="card-{{ $card->id }}" data-test="remark-card">
                    <div class="mb-3 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <flux:heading>{{ $card->student->first_name }} {{ $card->student->last_name }}</flux:heading>
                        <div class="flex flex-wrap gap-x-4 text-sm tabular-nums text-zinc-500 dark:text-zinc-400">
                            @if ($card->average !== null)
                                <span>{{ __('Average :avg', ['avg' => $fmt($card->average)]) }}</span>
                            @endif
                            @if ($card->aggregate !== null)
                                <span>{{ __('Aggregate :agg', ['agg' => $card->aggregate]) }}</span>
                            @endif
                            <span>{{ $days['total'] > 0 ? __('Present :p of :t days', ['p' => $days['present'], 't' => $days['total']]) : __('No register days yet') }}</span>
                            @if ($locked)
                                <flux:badge size="sm" color="green">{{ $card->status->label() }}</flux:badge>
                            @endif
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-[1fr_12rem]">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-300" for="tr-{{ $card->student_id }}">{{ __('Class teacher\'s remark') }}</label>
                            <textarea id="tr-{{ $card->student_id }}" rows="2" maxlength="400" wire:model.live.blur="remarks.{{ $key }}.teacher_remark" @disabled($locked) class="{{ $field }}"
                                placeholder="{{ __('e.g. Hardworking and kind to others; should read more at home.') }}"></textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-1">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-300" for="cd-{{ $card->student_id }}">{{ __('Conduct') }}</label>
                                <select id="cd-{{ $card->student_id }}" wire:model.live="remarks.{{ $key }}.conduct" @disabled($locked) class="{{ $field }}">
                                    <option value="">{{ __('Choose') }}</option>
                                    @foreach (\App\Enums\Conduct::cases() as $c)
                                        <option value="{{ $c->value }}">{{ $c->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-300" for="in-{{ $card->student_id }}">{{ __('Interest') }}</label>
                                <input id="in-{{ $card->student_id }}" type="text" maxlength="100" wire:model.live.blur="remarks.{{ $key }}.interest" @disabled($locked) class="{{ $field }}" placeholder="{{ __('e.g. Football, drawing') }}" />
                            </div>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-300" for="hr-{{ $card->student_id }}">{{ __('Head\'s remark') }}</label>
                            <textarea id="hr-{{ $card->student_id }}" rows="1" maxlength="400" wire:model.live.blur="remarks.{{ $key }}.head_remark" @disabled($locked || ! $this->isHead) class="{{ $field }}"
                                placeholder="{{ $this->isHead ? __('Your remark, or use the suggestions above') : __('Written by the head') }}"></textarea>
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</div>
