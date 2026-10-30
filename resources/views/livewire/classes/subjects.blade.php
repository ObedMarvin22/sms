<div class="flex max-w-5xl flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Class subjects') }}</flux:heading>
            <flux:text>{{ __('Choose the subjects each class takes. Only subjects taught at the class\'s level are listed.') }}</flux:text>
        </div>
        <flux:button wire:click="applyDefaults" icon="sparkles" data-test="apply-defaults">{{ __('Give every class its level\'s subjects') }}</flux:button>
    </div>

    @if ($this->classes->isEmpty())
        <flux:callout icon="rectangle-group">
            <flux:callout.heading>{{ __('No classes yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Create classes first, then choose their subjects here.') }}</flux:callout.text>
        </flux:callout>
    @else
        <div class="grid gap-6 md:grid-cols-[14rem_1fr]">
            <nav class="flex flex-col gap-1" data-test="class-list">
                @foreach ($this->classes as $class)
                    <button type="button" wire:click="selectClass({{ $class->id }})" wire:key="pick-{{ $class->id }}"
                        @class([
                            'flex items-center justify-between rounded-md px-3 py-2 text-start text-sm',
                            'bg-accent text-accent-foreground' => $class->id === $classId,
                            'hover:bg-zinc-100 dark:hover:bg-zinc-700' => $class->id !== $classId,
                        ])>
                        <span>{{ $class->name }}</span>
                        <span class="text-xs opacity-75" data-test="subject-count">{{ $class->subjects_count }}</span>
                    </button>
                @endforeach
            </nav>

            @if ($this->currentClass)
                <form wire:submit="save" class="flex flex-col gap-4">
                    <flux:heading size="lg">{{ $this->currentClass->name }}
                        <flux:badge size="sm" class="ms-2">{{ $this->currentClass->level->label() }}</flux:badge>
                    </flux:heading>

                    @if ($this->levelSubjects->isEmpty())
                        <flux:text>{{ __('No subjects are taught at this level yet. Add them on the Subjects screen.') }}</flux:text>
                    @else
                        <flux:checkbox.group wire:model="selected" class="grid gap-2 sm:grid-cols-2">
                            @foreach ($this->levelSubjects as $subject)
                                <flux:checkbox :value="(string) $subject->id" :label="$subject->name" wire:key="subject-{{ $subject->id }}" />
                            @endforeach
                        </flux:checkbox.group>
                    @endif
                    <flux:error name="subjects" />

                    <div>
                        <flux:button type="submit" variant="primary" data-test="save-class-subjects">{{ __('Save') }}</flux:button>
                    </div>
                </form>
            @endif
        </div>
    @endif
</div>
