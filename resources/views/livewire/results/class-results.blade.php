@php
    $level = $this->selectedStream?->schoolClass->level;
    $aggregates = $level !== null && isset(\App\Support\Academic\Aggregates::RULES[$level->value]);
    $positions = $this->showPositions();
    $fmt = fn ($v) => $v === null ? '–' : rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
@endphp

<div class="flex max-w-full flex-col gap-6">
    <div class="flex max-w-5xl flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Results') }}</flux:heading>
            <flux:text>
                {{ __('Each student\'s term result per subject, from the submitted mark sheets.') }}
                @if ($this->term) {{ $this->term->name }}. @endif
            </flux:text>
        </div>
        @if ($this->streams->count() > 1)
            <div class="w-full sm:w-56">
                <flux:select wire:model.live="streamId" :label="__('Class')" data-test="class">
                    @foreach ($this->streams as $s)
                        <flux:select.option :value="$s->id">{{ $s->full_name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endif
    </div>

    @if ($this->term === null)
        <flux:callout variant="warning" icon="calendar-days" class="max-w-5xl">
            <flux:callout.heading>{{ __('No current term') }}</flux:callout.heading>
        </flux:callout>
    @elseif ($this->selectedStream === null)
        <flux:callout icon="user-group" class="max-w-5xl">
            <flux:callout.heading>{{ __('No class to show') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Results appear here for the class you are class teacher of.') }}</flux:callout.text>
        </flux:callout>
    @elseif ($cards->isEmpty())
        <flux:callout icon="clipboard-document-check" class="max-w-5xl" data-test="no-results">
            <flux:callout.heading>{{ __('No results for :class yet', ['class' => $this->selectedStream->full_name]) }}</flux:callout.heading>
            <flux:callout.text>{{ __('Results appear as each subject\'s mark sheet is submitted. :done of :total subjects are submitted.', ['done' => $submitted, 'total' => $subjects->count()]) }}</flux:callout.text>
        </flux:callout>
    @else
        <flux:text size="sm" class="max-w-5xl" data-test="submitted-subjects">
            {{ __(':done of :total subjects submitted.', ['done' => $submitted, 'total' => $subjects->count()]) }}
            @if ($submitted < $subjects->count())
                {{ __('Averages and positions will change as the rest come in.') }}
            @endif
        </flux:text>

        <div class="max-w-full self-start overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-max border-separate border-spacing-0 text-sm">
                <thead>
                    <tr>
                        <th scope="col" class="sticky left-0 z-10 w-36 border-b border-zinc-200 bg-white px-4 py-2 text-left font-medium text-zinc-900 sm:w-52 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white">{{ __('Student') }}</th>
                        @foreach ($subjects as $subject)
                            <th scope="col" class="h-28 w-14 border-b border-zinc-200 px-1 pb-2 align-bottom font-normal dark:border-zinc-700">
                                <div class="mx-auto w-5 rotate-180 whitespace-nowrap text-left text-xs text-zinc-600 [writing-mode:vertical-rl] dark:text-zinc-300" title="{{ $subject->name }}">{{ $subject->code ?? $subject->name }}</div>
                            </th>
                        @endforeach
                        <th scope="col" class="w-20 border-b border-l border-zinc-200 bg-zinc-50 px-3 py-2 text-center font-medium dark:border-zinc-700 dark:bg-zinc-800/60">{{ __('Average') }}</th>
                        @if ($aggregates)
                            <th scope="col" class="w-20 border-b border-zinc-200 bg-zinc-50 px-3 py-2 text-center font-medium dark:border-zinc-700 dark:bg-zinc-800/60" title="{{ __('Core subjects plus the best others; lower is better') }}">{{ __('Aggregate') }}</th>
                        @endif
                        @if ($positions)
                            <th scope="col" class="w-20 border-b border-zinc-200 bg-zinc-50 px-3 py-2 text-center font-medium dark:border-zinc-700 dark:bg-zinc-800/60">{{ __('Position') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cards as $card)
                        <tr wire:key="card-{{ $card->id }}" data-test="result-row">
                            <th scope="row" class="sticky left-0 z-10 max-w-36 truncate border-b border-zinc-100 bg-white px-4 py-1.5 text-left font-medium text-zinc-900 sm:max-w-52 dark:border-zinc-800 dark:bg-zinc-900 dark:text-white" title="{{ $card->student->full_name }}">
                                <a href="{{ route('results.report-card', $card) }}" target="_blank" rel="noopener" class="hover:underline focus-visible:underline focus-visible:outline-none" data-test="preview">
                                    {{ $card->student->last_name }}, {{ $card->student->first_name }}
                                </a>
                            </th>
                            @foreach ($subjects as $subject)
                                @php($result = $results->get($card->student_id.'-'.$subject->id))
                                <td class="border-b border-zinc-100 px-1 py-1 text-center tabular-nums dark:border-zinc-800"
                                    title="{{ $result ? $subject->name.': '.$fmt($result->total).($result->grade ? ' ('.$result->grade.', '.$result->grade_label.')' : '') : $subject->name.': '.__('not submitted') }}">
                                    @if ($result)
                                        <span class="font-semibold text-zinc-900 dark:text-white">{{ $result->grade ?? $fmt($result->total) }}</span>
                                        @if ($result->grade)
                                            <span class="block text-[11px] text-zinc-500 dark:text-zinc-400">{{ $fmt($result->total) }}</span>
                                        @endif
                                    @else
                                        <span class="text-zinc-300 dark:text-zinc-600">·</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="border-b border-l border-zinc-100 bg-zinc-50 px-3 py-1 text-center font-medium tabular-nums dark:border-zinc-800 dark:bg-zinc-800/60">{{ $fmt($card->average) }}</td>
                            @if ($aggregates)
                                <td class="border-b border-zinc-100 bg-zinc-50 px-3 py-1 text-center tabular-nums dark:border-zinc-800 dark:bg-zinc-800/60">{{ $card->aggregate ?? '–' }}</td>
                            @endif
                            @if ($positions)
                                <td class="border-b border-zinc-100 bg-zinc-50 px-3 py-1 text-center tabular-nums dark:border-zinc-800 dark:bg-zinc-800/60">{{ $card->position ? \App\Support\Academic\Ranking::ordinal($card->position) : '–' }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <flux:text size="sm" class="max-w-5xl">
            {{ __('Each box shows the grade and, under it, the term total out of 100. A dot means that subject is not submitted yet. Click a name to see their report card.') }}
            @if ($aggregates)
                {{ __('The aggregate adds the core subjects\' grade points and the best others; lower is better.') }}
            @endif
        </flux:text>
    @endif
</div>
