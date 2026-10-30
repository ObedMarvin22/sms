<div class="flex max-w-5xl flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Teaching assignments') }}</flux:heading>
        <flux:text>
            {{ __('Who teaches each subject, and who is class teacher, for :year.', ['year' => $this->year?->name ?? __('the current year')]) }}
        </flux:text>
    </div>

    @if ($this->year === null)
        <flux:callout variant="warning" icon="calendar-days">
            <flux:callout.text>{{ __('Set a current academic year first (Academic years).') }}</flux:callout.text>
        </flux:callout>
    @elseif ($this->classes->isEmpty())
        <flux:callout icon="rectangle-group">
            <flux:callout.text>{{ __('Create classes first.') }}</flux:callout.text>
        </flux:callout>
    @else
        <div class="grid gap-6 md:grid-cols-[14rem_1fr]">
            <nav class="flex flex-col gap-1" data-test="stream-list">
                @foreach ($this->classes as $class)
                    @foreach ($class->streams as $s)
                        <button type="button" wire:click="selectStream({{ $s->id }})" wire:key="pick-{{ $s->id }}"
                            @class([
                                'flex items-center justify-between rounded-md px-3 py-2 text-start text-sm',
                                'bg-accent text-accent-foreground' => $s->id === $streamId,
                                'hover:bg-zinc-100 dark:hover:bg-zinc-700' => $s->id !== $streamId,
                            ])>
                            <span>{{ $class->name }}{{ $s->name }}</span>
                            @if (in_array($s->id, $this->incompleteStreamIds, true))
                                <span class="size-2 rounded-full bg-amber-500" title="{{ __('Missing teachers') }}" data-test="incomplete-{{ $s->id }}"></span>
                            @endif
                        </button>
                    @endforeach
                @endforeach
            </nav>

            @if ($this->selectedStream)
                <div class="flex flex-col gap-4">
                    <flux:heading size="lg">{{ $this->selectedStream->full_name }}</flux:heading>

                    <flux:select :label="__('Class teacher')" wire:change="assignClassTeacher($event.target.value)" data-test="class-teacher">
                        <flux:select.option value="">{{ __('— none —') }}</flux:select.option>
                        @foreach ($this->teachers as $teacher)
                            <flux:select.option :value="$teacher->id" :selected="$teacher->id === $this->classTeacherId">{{ $teacher->full_name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:separator />

                    @forelse ($this->selectedStream->schoolClass->subjects as $subject)
                        <div class="grid items-center gap-2 sm:grid-cols-[14rem_1fr]" wire:key="row-{{ $subject->id }}">
                            <flux:text>{{ $subject->name }}</flux:text>
                            <flux:select wire:change="assignTeacher({{ $subject->id }}, $event.target.value)" data-test="teacher-{{ $subject->id }}">
                                <flux:select.option value="">{{ __('— no teacher —') }}</flux:select.option>
                                @foreach ($this->teachers as $teacher)
                                    <flux:select.option :value="$teacher->id" :selected="($this->assigned[$subject->id] ?? null) === $teacher->id">{{ $teacher->full_name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    @empty
                        <flux:text>{{ __('This class has no subjects yet (Class subjects).') }}</flux:text>
                    @endforelse
                </div>
            @endif
        </div>
    @endif
</div>
