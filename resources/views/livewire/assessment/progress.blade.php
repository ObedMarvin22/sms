@use('App\Enums\SheetProgress')
@php
    $overview = $this->overview;
    $counts = $overview->counts();
    $total = array_sum($counts);

    // One look per state, used by the grid, the phone list and the legend.
    $look = [
        SheetProgress::NotStarted->value => 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400',
        SheetProgress::InProgress->value => 'bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:ring-amber-900',
        SheetProgress::Ready->value => 'bg-sky-50 text-sky-800 ring-1 ring-inset ring-sky-200 dark:bg-sky-950/50 dark:text-sky-300 dark:ring-sky-900',
        SheetProgress::Submitted->value => 'bg-green-600 text-white dark:bg-green-500 dark:text-green-950',
        SheetProgress::Reopened->value => 'bg-orange-100 text-orange-800 ring-1 ring-inset ring-orange-300 dark:bg-orange-950/50 dark:text-orange-300 dark:ring-orange-900',
    ];
    $mark = fn ($cell) => match ($cell->progress) {
        SheetProgress::NotStarted => '–',
        SheetProgress::InProgress => $cell->percent().'%',
        SheetProgress::Ready => __('Ready'),
        SheetProgress::Submitted => '✓',
        SheetProgress::Reopened => __('Open'),
    };
    $describe = fn ($cell) => $cell->subject->name.', '.$cell->stream->full_name.': '.$cell->progress->label()
        .($cell->progress === SheetProgress::InProgress ? ' ('.$cell->filled.' of '.$cell->expected.' done)' : '')
        .'. '.($cell->teacher ? __('Teacher: :name', ['name' => $cell->teacher]) : __('No teacher assigned'));
@endphp

<div class="flex max-w-full flex-col gap-6">
    <div class="flex max-w-5xl flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Marks progress') }}</flux:heading>
            <flux:text>{{ __('Every class and subject this term: who teaches it, how far the marks have got, and what is still missing.') }}</flux:text>
        </div>
        @if ($this->terms->isNotEmpty())
            <div class="w-full sm:w-64">
                <flux:select wire:model.live="termId" :label="__('Term')" data-test="term">
                    @foreach ($this->terms as $t)
                        <flux:select.option :value="$t->id">{{ $t->name }}, {{ $t->academicYear->name }}{{ $t->is_current ? ' ('.__('current').')' : '' }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endif
    </div>

    @if ($this->term === null)
        <flux:callout variant="warning" icon="calendar-days" class="max-w-5xl">
            <flux:callout.heading>{{ __('No current term') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Set the current term under Academic years to follow marks.') }}</flux:callout.text>
        </flux:callout>
    @elseif ($total === 0)
        <flux:callout icon="chart-pie" class="max-w-5xl" data-test="nothing">
            <flux:callout.heading>{{ __('No mark sheets this term') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Mark sheets appear once classes have subjects and their level has marked assessment, under Settings, Assessment.') }}</flux:callout.text>
        </flux:callout>
    @else
        {{-- Summary: the headline number, then one chip per state that also filters --}}
        <section class="flex max-w-5xl flex-col gap-4 rounded-xl border border-zinc-200 p-4 sm:p-5 dark:border-zinc-700" aria-label="{{ __('Summary') }}">
            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="text-3xl font-semibold tabular-nums text-zinc-900 dark:text-white" data-test="submitted-count">{{ $counts[SheetProgress::Submitted->value] }}</span>
                <span class="text-zinc-600 dark:text-zinc-300">{{ __('of :total sheets submitted', ['total' => $total]) }}</span>
            </div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800" role="img"
                aria-label="{{ __(':done of :total submitted', ['done' => $counts[SheetProgress::Submitted->value], 'total' => $total]) }}">
                <div class="h-full rounded-full bg-green-600 dark:bg-green-500" style="width: {{ $total ? round($counts[SheetProgress::Submitted->value] / $total * 100, 1) : 0 }}%"></div>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach ($progressCases as $case)
                    <button type="button" wire:click="$set('state', '{{ $state === $case->value ? '' : $case->value }}')"
                        @class([
                            'inline-flex items-center gap-2 rounded-full border px-3 py-1 text-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zinc-500',
                            'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' => $state === $case->value,
                            'border-zinc-200 text-zinc-700 hover:border-zinc-400 dark:border-zinc-700 dark:text-zinc-200' => $state !== $case->value,
                        ])
                        aria-pressed="{{ $state === $case->value ? 'true' : 'false' }}" data-test="chip-{{ $case->value }}">
                        <span class="{{ $look[$case->value] }} inline-flex h-4 min-w-4 items-center justify-center rounded px-1 text-[10px] font-semibold" aria-hidden="true"></span>
                        {{ $case->label() }}
                        <span class="tabular-nums opacity-70">{{ $counts[$case->value] }}</span>
                    </button>
                @endforeach
            </div>
        </section>

        {{-- Filters --}}
        <div class="grid max-w-5xl gap-3 sm:grid-cols-3">
            <flux:select wire:model.live="level" :label="__('Level')">
                <flux:select.option value="">{{ __('All levels') }}</flux:select.option>
                @foreach ($overview->levels as $l)
                    <flux:select.option :value="$l['level']->value">{{ $l['level']->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="state" :label="__('Show')">
                <flux:select.option value="">{{ __('Every sheet') }}</flux:select.option>
                <flux:select.option value="outstanding">{{ __('Not submitted yet') }}</flux:select.option>
                @foreach ($progressCases as $case)
                    <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
                @endforeach
                <flux:select.option value="no_teacher">{{ __('No teacher assigned') }}</flux:select.option>
            </flux:select>
            <flux:select wire:model.live="teacher" :label="__('Teacher')">
                <flux:select.option value="">{{ __('Every teacher') }}</flux:select.option>
                @foreach ($this->teacherOptions() as $id => $name)
                    <flux:select.option :value="(string) $id">{{ $name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @php($shown = 0)
        @foreach ($overview->levels as $l)
            @continue($level !== '' && $l['level']->value !== $level)
            @php($rows = collect($l['streams'])->filter(fn ($s) => ! $this->filtering() || collect($overview->cellsByStream[$s->id] ?? [])->contains(fn ($c) => $this->matches($c))))
            @continue($this->filtering() && ($l['note'] !== null || $rows->isEmpty()))
            @php($shown++)

            <section class="flex flex-col gap-3" wire:key="level-{{ $l['level']->value }}" data-test="level-{{ $l['level']->value }}">
                <div class="flex items-baseline gap-2">
                    <flux:heading size="lg">{{ $l['level']->label() }}</flux:heading>
                    @if ($l['commentsOnly'])
                        <flux:text size="sm">{{ __('comments') }}</flux:text>
                    @endif
                </div>

                @if ($l['note'])
                    <flux:text>{{ __($l['note']) }}</flux:text>
                @else
                    {{-- Grid from tablet width up: classes down, subjects across --}}
                    <div class="hidden max-w-full self-start overflow-x-auto rounded-xl border border-zinc-200 md:block dark:border-zinc-700">
                        <table class="w-max border-separate border-spacing-0 text-sm">
                            <thead>
                                <tr>
                                    <th scope="col" class="sticky left-0 z-10 w-28 border-b border-zinc-200 bg-white px-4 py-2 text-left font-medium text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white">{{ __('Class') }}</th>
                                    @foreach ($l['subjects'] as $subject)
                                        <th scope="col" class="h-28 w-14 border-b border-zinc-200 px-1 pb-2 align-bottom font-normal dark:border-zinc-700">
                                            <div class="mx-auto w-5 [writing-mode:vertical-rl] rotate-180 whitespace-nowrap text-left text-xs text-zinc-600 dark:text-zinc-300" title="{{ $subject->name }}">{{ $subject->code ?? $subject->name }}</div>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $stream)
                                    <tr wire:key="row-{{ $stream->id }}">
                                        <th scope="row" class="sticky left-0 z-10 border-b border-zinc-100 bg-white px-4 py-1.5 text-left font-medium whitespace-nowrap text-zinc-900 dark:border-zinc-800 dark:bg-zinc-900 dark:text-white">{{ $stream->full_name }}</th>
                                        @foreach ($l['subjects'] as $subject)
                                            @php($cell = $overview->cell($stream->id, $subject->id))
                                            <td class="border-b border-zinc-100 px-1 py-1 text-center dark:border-zinc-800">
                                                @if ($cell)
                                                    <a href="{{ route($l['commentsOnly'] ? 'assessment.comments' : 'assessment.marks', [$stream, $subject]) }}" wire:navigate
                                                        title="{{ $describe($cell) }}" aria-label="{{ $describe($cell) }}"
                                                        @class([
                                                            $look[$cell->progress->value],
                                                            'relative inline-flex h-7 w-12 items-center justify-center rounded-md text-xs font-semibold tabular-nums transition hover:brightness-95 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-zinc-500',
                                                            'opacity-20' => $this->filtering() && ! $this->matches($cell),
                                                        ]) data-test="cell">
                                                        {{ $mark($cell) }}
                                                        @if ($cell->teacherId === null && ! in_array($cell->progress, [SheetProgress::Submitted], true))
                                                            <span class="absolute -right-1 -top-1 size-2.5 rounded-full bg-red-500 ring-2 ring-white dark:ring-zinc-900" aria-hidden="true"></span>
                                                        @endif
                                                    </a>
                                                @else
                                                    <span class="sr-only">{{ __('Not taken') }}</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Phones: a list per class --}}
                    <div class="flex flex-col gap-3 md:hidden">
                        @foreach ($rows as $stream)
                            @php($cells = collect($overview->cellsByStream[$stream->id] ?? [])->filter(fn ($c) => ! $this->filtering() || $this->matches($c)))
                            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700" wire:key="list-{{ $stream->id }}">
                                <div class="border-b border-zinc-200 px-4 py-2 font-medium text-zinc-900 dark:border-zinc-700 dark:text-white">{{ $stream->full_name }}</div>
                                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                    @foreach ($cells as $cell)
                                        <li>
                                            <a href="{{ route($l['commentsOnly'] ? 'assessment.comments' : 'assessment.marks', [$cell->stream, $cell->subject]) }}" wire:navigate class="flex items-center gap-3 px-4 py-2.5">
                                                <div class="min-w-0 flex-1">
                                                    <div class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $cell->subject->name }}</div>
                                                    <div @class(['truncate text-xs', 'text-zinc-500 dark:text-zinc-400' => $cell->teacher, 'text-red-600 dark:text-red-400' => ! $cell->teacher])>{{ $cell->teacher ?? __('No teacher assigned') }}</div>
                                                </div>
                                                <span class="{{ $look[$cell->progress->value] }} inline-flex h-6 min-w-12 items-center justify-center rounded-md px-2 text-xs font-semibold tabular-nums">{{ $mark($cell) }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach

        @if ($shown === 0)
            <div class="flex max-w-5xl flex-wrap items-center gap-3" data-test="no-match">
                <flux:text>{{ __('No sheets match these filters.') }}</flux:text>
                <flux:button size="sm" variant="ghost" wire:click="clearFilters">{{ __('Clear filters') }}</flux:button>
            </div>
        @endif

        <flux:text size="sm" class="max-w-5xl">
            {{ __('– not started (no tasks or comments yet), a percentage in progress, Ready when every box is filled, ✓ submitted, Open when the head has unlocked it. A red dot means no teacher is assigned. Click a box to open the sheet.') }}
        </flux:text>
    @endif
</div>
