<div class="flex max-w-3xl flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Set up your school') }}</flux:heading>
        <flux:text>{{ __('Work through these steps in order. Each one opens the screen where it is done.') }}</flux:text>
    </div>

    @if (session('status'))
        <flux:callout variant="warning" icon="exclamation-triangle" data-test="setup-status">
            <flux:callout.text>{{ session('status') }}</flux:callout.text>
        </flux:callout>
    @endif

    @if ($progress->isComplete())
        <flux:callout variant="success" icon="check-circle" data-test="setup-complete">
            <flux:callout.heading>{{ __('Your school is ready') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Every step is done. You can change any of it later from the School setup menu.') }}</flux:callout.text>
        </flux:callout>
    @endif

    <ol class="flex flex-col gap-3">
        @foreach ($steps as $i => $step)
            @php($done = $progress->isDone($step))
            @php($open = $progress->isOpen($step))
            <li wire:key="step-{{ $step->value }}" data-test="step-{{ $step->value }}" data-state="{{ $done ? 'done' : ($open ? 'open' : 'locked') }}"
                @class([
                    'flex flex-wrap items-center gap-4 rounded-lg border p-4',
                    'border-zinc-200 dark:border-zinc-700' => $open,
                    'border-dashed border-zinc-200 opacity-60 dark:border-zinc-700' => ! $open,
                ])>
                <span @class([
                    'flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold',
                    'bg-accent text-accent-foreground' => $done,
                    'bg-zinc-100 dark:bg-zinc-700' => ! $done,
                ])>{{ $done ? '✓' : $i + 1 }}</span>

                <div class="min-w-48 flex-1">
                    <flux:heading>{{ __($step->label()) }}</flux:heading>
                    <flux:text size="sm">{{ __($step->description()) }}</flux:text>
                </div>

                @if ($open)
                    <div class="flex flex-wrap gap-2">
                        @if (! $done && $step === \App\Enums\SetupStep::Classes)
                            <flux:button size="sm" wire:click="generateClasses" data-test="quick-classes">{{ __('Create the usual classes') }}</flux:button>
                        @elseif (! $done && $step === \App\Enums\SetupStep::Subjects)
                            <flux:button size="sm" wire:click="generateSubjects" data-test="quick-subjects">{{ __('Add preset subjects') }}</flux:button>
                        @elseif (! $done && $step === \App\Enums\SetupStep::ClassSubjects)
                            <flux:button size="sm" wire:click="applyClassSubjects" data-test="quick-class-subjects">{{ __('Give every class its level\'s subjects') }}</flux:button>
                        @endif
                        <flux:button size="sm" :variant="$done ? 'ghost' : 'primary'" :href="route($step->route())" wire:navigate>
                            {{ $done ? __('Review') : __('Open') }}
                        </flux:button>
                    </div>
                @else
                    <flux:text size="sm">{{ __('Finish the steps above first.') }}</flux:text>
                @endif
            </li>
        @endforeach
    </ol>
</div>
