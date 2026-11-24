{{-- Shared header for a class and subject: back link, title, term, the Tasks / Marks tabs, and
     where the sheet stands (open, submitted, reopened) with Submit and Unlock. Rendered inside the
     host Livewire component, so wire: calls go to it (ManagesSheetLock). --}}
@props([
    'stream', 'subject', 'term', 'canManage', 'active',
    'record' => null, 'canUnlock' => false, 'problems' => [], 'commentsOnly' => false,
])

@php($locked = $record?->isLocked() ?? false)

<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-3">
        <flux:button :href="route('assessment.index')" wire:navigate variant="ghost" size="sm" icon="arrow-left" class="-ms-2 self-start">{{ __('Marks') }}</flux:button>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div class="min-w-0">
                <flux:heading size="xl">{{ $subject->name }}, {{ $stream->full_name }}</flux:heading>
                <flux:text>{{ $term->name }}, {{ $term->starts_on->format('j M') }} to {{ $term->ends_on->format('j M Y') }}</flux:text>
            </div>
            <nav class="flex rounded-lg bg-zinc-100 p-0.5 text-sm dark:bg-zinc-800" aria-label="{{ __('Sheet') }}">
                @foreach ($commentsOnly ? ['assessment.comments' => __('Comments')] : ['assessment.tasks' => __('Tasks'), 'assessment.marks' => __('Marks')] as $route => $label)
                    <a href="{{ route($route, [$stream, $subject]) }}" wire:navigate
                        @if ($active === $route) aria-current="page" @endif
                        @class([
                            'rounded-md px-3 py-1.5 font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-zinc-500',
                            'bg-white text-zinc-900 shadow-xs dark:bg-zinc-700 dark:text-white' => $active === $route,
                            'text-zinc-600 hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-white' => $active !== $route,
                        ])>{{ $label }}</a>
                @endforeach
            </nav>
        </div>
    </div>

    {{-- Where the sheet stands --}}
    <div @class([
        'flex flex-col gap-3 rounded-xl border px-4 py-3 sm:px-5',
        'border-green-200 bg-green-50 dark:border-green-900 dark:bg-green-950/40' => $locked,
        'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/40' => ! $locked && $record,
        'border-zinc-200 dark:border-zinc-700' => ! $record,
    ]) data-test="sheet-status">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex min-w-0 items-start gap-3">
                <flux:icon :name="$locked ? 'lock-closed' : ($record ? 'lock-open' : 'pencil-square')" variant="mini"
                    @class(['mt-0.5 shrink-0', 'text-green-700 dark:text-green-400' => $locked, 'text-amber-700 dark:text-amber-400' => ! $locked && $record, 'text-zinc-400' => ! $record]) />
                <div class="min-w-0 text-sm">
                    @if ($locked)
                        <div class="font-medium text-zinc-900 dark:text-white">{{ __('Submitted and locked') }}</div>
                        <div class="text-zinc-600 dark:text-zinc-300">
                            {{ __($commentsOnly ? 'By :name on :when. Comments cannot change unless the head unlocks them.' : 'By :name on :when. Marks and tasks cannot change unless the head unlocks them.', ['name' => $record->submitter->name ?? __('a former user'), 'when' => $record->submitted_at?->format('j M, H:i')]) }}
                        </div>
                    @elseif ($record)
                        <div class="font-medium text-zinc-900 dark:text-white">{{ __('Unlocked for corrections') }}</div>
                        <div class="text-zinc-600 dark:text-zinc-300">
                            {{ __('By :name on :when: “:reason”. Every change is logged. Submit again when done.', ['name' => $record->unlocker->name ?? __('a former user'), 'when' => $record->unlocked_at?->format('j M, H:i'), 'reason' => $record->unlock_reason]) }}
                        </div>
                    @else
                        <div class="font-medium text-zinc-900 dark:text-white">{{ __('Not submitted yet') }}</div>
                        <div class="text-zinc-600 dark:text-zinc-300">
                            @if ($commentsOnly)
                                {{ $problems === [] ? __('Every child has a comment. Submit to lock them for the report card.') : __('Submit when every child has a comment.') }}
                            @else
                                {{ $problems === [] ? __('Every box is filled. Submit to lock the marks for the report card.') : __('Submit when every student has a mark, A or X in every box.') }}
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            @if ($locked && $canUnlock)
                <flux:modal.trigger name="unlock-sheet">
                    <flux:button size="sm" icon="lock-open" data-test="unlock">{{ __('Unlock') }}</flux:button>
                </flux:modal.trigger>
            @elseif (! $locked && $canManage)
                <flux:button size="sm" :variant="$problems === [] ? 'primary' : 'filled'" icon="check" wire:click="submitSheet"
                    :disabled="$problems !== []" data-test="submit-sheet">
                    {{ $record ? __('Submit again') : ($commentsOnly ? __('Submit comments') : __('Submit marks')) }}
                </flux:button>
            @endif
        </div>

        @if (! $locked && $canManage && $problems !== [])
            <details class="group text-sm" data-test="problems">
                <summary class="cursor-pointer select-none font-medium text-zinc-700 marker:text-zinc-400 dark:text-zinc-200">
                    {{ trans_choice('{1} 1 thing to finish first|[2,*] :count things to finish first', count($problems), ['count' => count($problems)]) }}
                </summary>
                <ul class="mt-2 flex flex-col gap-1 ps-4 text-zinc-600 dark:text-zinc-300">
                    @foreach ($problems as $problem)
                        <li class="list-disc">{{ $problem }}</li>
                    @endforeach
                </ul>
            </details>
        @endif
    </div>

    @unless ($canManage)
        <flux:callout icon="eye" variant="secondary" data-test="view-only">
            <flux:callout.text>{{ __('You are the class teacher, so you can see this sheet. Only the subject teacher or the head can change it.') }}</flux:callout.text>
        </flux:callout>
    @endunless

    @if ($canUnlock)
        <flux:modal name="unlock-sheet" class="w-full md:w-[30rem]">
            <form wire:submit="unlockSheet" class="flex flex-col gap-4">
                <div>
                    <flux:heading size="lg">{{ __('Unlock :subject, :stream?', ['subject' => $subject->name, 'stream' => $stream->full_name]) }}</flux:heading>
                    <flux:text class="mt-1">{{ __('The subject teacher can then change marks and tasks, and must submit again. The reason is shown on the sheet and kept in the activity log.') }}</flux:text>
                </div>
                <flux:textarea wire:model="unlockReason" :label="__('Reason')" rows="2" :placeholder="__('e.g. Wrong exam mark for Kofi Mensah')" data-test="unlock-reason" />
                <flux:error name="unlock_reason" />
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary" data-test="confirm-unlock">{{ __('Unlock') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>
