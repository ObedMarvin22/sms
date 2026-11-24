{{-- Shared header for a class and subject: back link, title, term, and the Tasks / Marks tabs. --}}
@props(['stream', 'subject', 'term', 'canManage', 'active'])

<div class="flex flex-col gap-3">
    <flux:button :href="route('assessment.index')" wire:navigate variant="ghost" size="sm" icon="arrow-left" class="-ms-2 self-start">{{ __('Marks') }}</flux:button>
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="min-w-0">
            <flux:heading size="xl">{{ $subject->name }}, {{ $stream->full_name }}</flux:heading>
            <flux:text>{{ $term->name }}, {{ $term->starts_on->format('j M') }} to {{ $term->ends_on->format('j M Y') }}</flux:text>
        </div>
        <nav class="flex rounded-lg bg-zinc-100 p-0.5 text-sm dark:bg-zinc-800" aria-label="{{ __('Sheet') }}">
            @foreach (['assessment.tasks' => __('Tasks'), 'assessment.marks' => __('Marks')] as $route => $label)
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
    @unless ($canManage)
        <flux:callout icon="eye" variant="secondary" data-test="view-only">
            <flux:callout.text>{{ __('You are the class teacher, so you can see this sheet. Only the subject teacher or the head can change it.') }}</flux:callout.text>
        </flux:callout>
    @endunless
</div>
