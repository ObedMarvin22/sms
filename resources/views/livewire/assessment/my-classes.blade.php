<div class="flex max-w-4xl flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Marks') }}</flux:heading>
            <flux:text>
                @if ($this->term)
                    {{ __('Tasks you set for each class this term: :term, :start to :end.', ['term' => $this->term->name, 'start' => $this->term->starts_on->format('j M'), 'end' => $this->term->ends_on->format('j M Y')]) }}
                @else
                    {{ __('Tasks you set for each class this term.') }}
                @endif
            </flux:text>
        </div>
        @if ($this->term && ($this->sheets->count() > 8 || $search !== ''))
            <div class="w-full sm:w-64">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Class, subject or teacher')" :aria-label="__('Search')" clearable />
            </div>
        @endif
    </div>

    @if ($this->term === null)
        <flux:callout variant="warning" icon="calendar-days">
            <flux:callout.heading>{{ __('No current term') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Tasks belong to a term. Ask an administrator to set the current term under Academic years.') }}</flux:callout.text>
        </flux:callout>
    @elseif ($this->sheets->isEmpty() && $search !== '')
        <flux:text>{{ __('Nothing matches ":search".', ['search' => $search]) }}</flux:text>
    @elseif ($this->sheets->isEmpty())
        <flux:callout icon="book-open" data-test="no-sheets">
            <flux:callout.heading>{{ __('No subjects to teach yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Your classes appear here once an administrator assigns you a subject and class under Teaching assignments.') }}</flux:callout.text>
        </flux:callout>
    @else
        @foreach ($this->sheets->groupBy(fn ($s) => $s['stream']->id) as $sheets)
            <section class="flex flex-col gap-2" wire:key="stream-{{ $sheets->first()['stream']->id }}">
                <flux:heading size="lg">{{ $sheets->first()['stream']->full_name }}</flux:heading>
                <ul class="divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                    @foreach ($sheets as $sheet)
                        <li wire:key="sheet-{{ $sheet['stream']->id }}-{{ $sheet['subject']->id }}">
                            <a href="{{ route($sheet['comments'] ? 'assessment.comments' : 'assessment.tasks', [$sheet['stream'], $sheet['subject']]) }}" wire:navigate
                                class="flex items-center gap-4 px-4 py-3 transition-colors hover:bg-zinc-50 focus-visible:bg-zinc-50 focus-visible:outline-none dark:hover:bg-zinc-800/60 dark:focus-visible:bg-zinc-800/60"
                                data-test="sheet">
                                <div class="min-w-0 flex-1">
                                    <div class="truncate font-medium text-zinc-900 dark:text-white">{{ $sheet['subject']->name }}</div>
                                    @if (auth()->user()->can(\App\Enums\PermissionKey::GradesApprove->value))
                                        <div class="truncate text-sm text-zinc-500 dark:text-zinc-400">{{ $sheet['teacher'] ?? __('No teacher assigned') }}</div>
                                    @endif
                                </div>
                                @if ($sheet['status'])
                                    <flux:badge size="sm" :color="$sheet['status'] === \App\Enums\SheetStatus::Submitted ? 'green' : 'amber'" class="shrink-0" data-test="sheet-badge">{{ $sheet['status']->label() }}</flux:badge>
                                @endif
                                @unless ($sheet['manage'])
                                    <flux:badge size="sm" color="zinc" class="shrink-0">{{ __('View only') }}</flux:badge>
                                @endunless
                                <span class="shrink-0 text-sm tabular-nums text-zinc-500 dark:text-zinc-400">
                                    @if ($sheet['comments'])
                                        {{ __('Comments') }}
                                    @else
                                        {{ trans_choice('{0} No tasks yet|{1} :count task|[2,*] :count tasks', $sheet['tasks'], ['count' => $sheet['tasks']]) }}
                                    @endif
                                </span>
                                <flux:icon.chevron-right variant="micro" class="shrink-0 text-zinc-400" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    @endif
</div>
