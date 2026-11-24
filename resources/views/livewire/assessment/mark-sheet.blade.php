@php
    $sheet = $this->sheet;
    $scheme = $sheet->scheme;
    $level = $this->classStream()->schoolClass->level;
    $parts = $scheme?->components ?? collect();
    $fmt = fn (?float $v) => $v === null ? '–' : rtrim(rtrim(number_format($v, 1), '0'), '.');
@endphp

<div class="flex max-w-full flex-col gap-6">
    <div class="max-w-4xl">
        <x-assessment.sheet-header :stream="$this->classStream()" :subject="$this->subject()" :term="$this->term"
            :can-manage="$this->canManage" active="assessment.marks"
            :record="$this->sheetRecord" :can-unlock="$this->canUnlock" :problems="$this->problems" />
    </div>

    @if ($scheme === null)
        <flux:callout variant="warning" icon="chart-pie" class="max-w-4xl">
            <flux:callout.heading>{{ __(':level assessment is not set up yet', ['level' => $level->label()]) }}</flux:callout.heading>
            <flux:callout.text>{{ __('Marks go under the parts of the assessment. The head or an administrator sets these up under Settings, Assessment.') }}</flux:callout.text>
        </flux:callout>
    @elseif ($scheme->mode === \App\Enums\AssessmentMode::Descriptive)
        <flux:callout icon="chat-bubble-left-ellipsis" class="max-w-4xl">
            <flux:callout.heading>{{ __(':level is assessed with comments only', ['level' => $level->label()]) }}</flux:callout.heading>
            <flux:callout.text>{{ __('There are no marks to enter. Teachers write a comment on each child at the end of term.') }}</flux:callout.text>
        </flux:callout>
    @elseif ($sheet->tasks->isEmpty())
        <flux:callout icon="clipboard-document-list" class="max-w-4xl" data-test="no-tasks">
            <flux:callout.heading>{{ __('No tasks yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Each column on the mark sheet is a task, such as a class test. Add the tasks first, with what each is out of.') }}</flux:callout.text>
            @if ($this->canManage)
                <x-slot name="actions">
                    <flux:button :href="route('assessment.tasks', [$this->classStream(), $this->subject()])" wire:navigate size="sm">{{ __('Add tasks') }}</flux:button>
                </x-slot>
            @endif
        </flux:callout>
    @elseif ($sheet->rows === [])
        <flux:callout icon="user-group" class="max-w-4xl" data-test="no-students">
            <flux:callout.heading>{{ __('No students on this sheet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Students appear here once they are enrolled in :stream this term.', ['stream' => $this->classStream()->full_name]) }}</flux:callout.text>
        </flux:callout>
    @else
        <div class="flex max-w-4xl flex-wrap items-center justify-between gap-x-6 gap-y-2">
            @if ($this->editable)
                <flux:text size="sm">
                    {{ __('Type a mark, A for absent or X for exempt. Enter moves down, Tab moves across. Each mark saves when you leave the box.') }}
                </flux:text>
                <flux:text size="sm" class="tabular-nums" aria-live="polite">
                    <span wire:loading.delay wire:target="cells">{{ __('Saving…') }}</span>
                    <span wire:loading.remove wire:target="cells">{{ $errors->isEmpty() ? __('All marks saved') : __('Some marks were not saved') }}</span>
                </flux:text>
            @endif
        </div>

        @if ($errors->any())
            <flux:callout variant="danger" icon="exclamation-triangle" class="max-w-4xl" data-test="sheet-errors">
                <flux:callout.text>
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </flux:callout.text>
            </flux:callout>
        @endif

        <div class="max-w-full self-start overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700"
            x-data="{
                move(e) {
                    const el = e.target;
                    if (! el.matches('input[data-row]')) return;
                    const step = e.key === 'Enter' || e.key === 'ArrowDown' ? 1 : (e.key === 'ArrowUp' ? -1 : 0);
                    if (step === 0) return;
                    e.preventDefault();
                    let row = Number(el.dataset.row) + step;
                    let next;
                    while ((next = this.$root.querySelector(`input[data-row='${row}'][data-col='${el.dataset.col}']`))) {
                        if (! next.disabled) { next.focus(); next.select(); return; }
                        row += step;
                    }
                    el.blur();
                },
            }"
            x-on:keydown="move($event)">
            <table class="w-max border-separate border-spacing-0 text-sm">
                <thead>
                    <tr class="text-left align-bottom">
                        <th scope="col" class="sticky left-0 z-10 w-36 min-w-36 border-b sm:w-56 sm:min-w-48 border-zinc-200 bg-white px-4 py-2 font-medium text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white">
                            {{ __('Student') }}
                        </th>
                        @foreach ($sheet->tasks as $task)
                            @php($part = $parts->firstWhere('id', $task->assessment_component_id))
                            <th scope="col" class="w-20 border-b border-zinc-200 px-2 py-2 text-center font-normal dark:border-zinc-700" data-test="task-column">
                                <div class="mx-auto max-w-24 truncate font-medium text-zinc-900 dark:text-white" title="{{ $task->name }}">{{ $task->name }}</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('/ :max', ['max' => $task->max_mark]) }}</div>
                                <div class="mx-auto max-w-24 truncate text-xs text-zinc-400 dark:text-zinc-500">{{ $part?->name }}</div>
                            </th>
                        @endforeach
                        @foreach ($parts as $part)
                            <th scope="col" class="w-24 border-b border-l border-zinc-200 bg-zinc-50 px-2 py-2 text-center font-normal dark:border-zinc-700 dark:bg-zinc-800/60">
                                <div class="mx-auto max-w-24 text-balance font-medium leading-tight text-zinc-900 dark:text-white">{{ $part->name }}</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('/ :weight', ['weight' => $part->weight]) }}</div>
                            </th>
                        @endforeach
                        <th scope="col" class="w-16 border-b border-l border-zinc-200 bg-zinc-50 px-3 py-2 text-center font-medium text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-white">
                            {{ __('Total') }}
                            <div class="text-xs font-normal text-zinc-500 dark:text-zinc-400">{{ __('/ 100') }}</div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sheet->rows as $r => $row)
                        @php($result = $sheet->results[$row->student->id])
                        <tr wire:key="row-{{ $row->student->id }}" @class(['text-zinc-400 dark:text-zinc-500' => ! $row->editable]) data-test="sheet-row">
                            <th scope="row" class="sticky left-0 z-10 max-w-36 border-b border-zinc-100 bg-white px-3 py-1.5 sm:max-w-56 sm:px-4 text-left font-normal dark:border-zinc-800 dark:bg-zinc-900">
                                <div @class(['truncate font-medium', 'text-zinc-900 dark:text-white' => $row->editable]) title="{{ $row->student->full_name }}">{{ $row->student->last_name }}, {{ $row->student->first_name }}</div>
                                @if ($row->note)
                                    <div class="text-xs">{{ $row->note }}</div>
                                @endif
                            </th>
                            @foreach ($sheet->tasks as $c => $task)
                                @php($key = \App\Support\Assessment\ScoreSheet::key($task->id, $row->student->id))
                                <td class="border-b border-zinc-100 px-1.5 py-1 text-center dark:border-zinc-800">
                                    <input type="text" inputmode="decimal" autocomplete="off" maxlength="6"
                                        wire:model.live.blur="cells.{{ $key }}"
                                        data-row="{{ $r }}" data-col="{{ $c }}"
                                        aria-label="{{ __(':student, :task, out of :max', ['student' => $row->student->full_name, 'task' => $task->name, 'max' => $task->max_mark]) }}"
                                        @disabled(! $this->editable || ! $row->editable)
                                        @error("cells.$key") aria-invalid="true" @enderror
                                        @class([
                                            'w-14 rounded-md border bg-white px-1.5 py-1 text-center tabular-nums text-zinc-900 outline-none transition-colors',
                                            'focus:border-zinc-500 focus:ring-2 focus:ring-zinc-500/20 dark:bg-zinc-900 dark:text-white',
                                            'disabled:border-transparent disabled:bg-transparent disabled:text-inherit',
                                            'border-zinc-200 dark:border-zinc-700' => ! $errors->has("cells.$key"),
                                            'border-red-500 ring-2 ring-red-500/20' => $errors->has("cells.$key"),
                                        ]) />
                                </td>
                            @endforeach
                            @foreach ($parts as $part)
                                <td class="border-b border-l border-zinc-100 bg-zinc-50 px-2 py-1 text-center tabular-nums dark:border-zinc-800 dark:bg-zinc-800/60">{{ $fmt($result['parts'][$part->id] ?? null) }}</td>
                            @endforeach
                            <td class="border-b border-l border-zinc-100 bg-zinc-50 px-3 py-1 text-center font-medium tabular-nums dark:border-zinc-800 dark:bg-zinc-800/60" data-test="total">{{ $fmt($result['total']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <flux:text size="sm" class="max-w-4xl">
            {{ __('Part scores add up the marks in each part: marks scored out of marks possible, times the part\'s weight. Absent counts as zero; exempt and empty boxes are left out until filled.') }}
        </flux:text>
    @endif
</div>
