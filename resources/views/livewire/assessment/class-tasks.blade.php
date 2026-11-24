<div class="flex max-w-4xl flex-col gap-6">
    <x-assessment.sheet-header :stream="$this->classStream()" :subject="$this->subject()" :term="$this->term"
        :can-manage="$this->canManage" active="assessment.tasks" />

    @php($scheme = $this->scheme)
    @php($level = $this->classStream()->schoolClass->level)

    @if ($scheme === null)
        <flux:callout variant="warning" icon="chart-pie" data-test="no-scheme">
            <flux:callout.heading>{{ __(':level assessment is not set up yet', ['level' => $level->label()]) }}</flux:callout.heading>
            <flux:callout.text>{{ __('Tasks go under the parts of the assessment (class work, tests, exam). The head or an administrator sets these up under Settings, Assessment.') }}</flux:callout.text>
            @can(\App\Enums\PermissionKey::SettingsManage->value)
                <x-slot name="actions">
                    <flux:button :href="route('assessment.schemes')" wire:navigate size="sm">{{ __('Set up assessment') }}</flux:button>
                </x-slot>
            @endcan
        </flux:callout>
    @elseif ($scheme->mode === \App\Enums\AssessmentMode::Descriptive)
        <flux:callout icon="chat-bubble-left-ellipsis" data-test="descriptive">
            <flux:callout.heading>{{ __(':level is assessed with comments only', ['level' => $level->label()]) }}</flux:callout.heading>
            <flux:callout.text>{{ __('There are no marked tasks. Teachers write a comment on each child at the end of term.') }}</flux:callout.text>
        </flux:callout>
    @else
        <div class="flex flex-col gap-4">
            @foreach ($scheme->components as $part)
                @php($partTasks = $this->tasks->where('assessment_component_id', $part->id))
                @php($full = $part->kind === \App\Enums\ComponentKind::Exam && $partTasks->isNotEmpty())
                <section class="rounded-xl border border-zinc-200 dark:border-zinc-700" wire:key="part-{{ $part->id }}" data-test="part-{{ $part->id }}">
                    <header class="flex items-center justify-between gap-3 px-4 py-3 sm:px-5">
                        <div class="flex min-w-0 flex-col sm:flex-row sm:items-baseline sm:gap-2">
                            <flux:heading size="lg" class="truncate">{{ $part->name }}</flux:heading>
                            <span class="shrink-0 text-sm tabular-nums text-zinc-500 dark:text-zinc-400">{{ __(':weight% of the term', ['weight' => $part->weight]) }}</span>
                        </div>
                        @if ($this->canManage && ! $full)
                            <flux:button size="sm" icon="plus" wire:click="startCreate({{ $part->id }})" data-test="add-task-{{ $part->id }}">{{ __('Add task') }}</flux:button>
                        @endif
                    </header>

                    @if ($partTasks->isEmpty())
                        <p class="border-t border-zinc-200 px-4 py-3 text-sm text-zinc-500 sm:px-5 dark:border-zinc-700 dark:text-zinc-400">
                            {{ $part->kind === \App\Enums\ComponentKind::Exam ? __('Add the exam once its date is set.') : __('No tasks yet. Add each piece of work you mark, with what it is out of.') }}
                        </p>
                    @else
                        <ul class="divide-y divide-zinc-200 border-t border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                            @foreach ($partTasks as $task)
                                <li class="flex items-center gap-3 px-4 py-2.5 sm:px-5" wire:key="task-{{ $task->id }}" data-test="task">
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate font-medium text-zinc-900 dark:text-white">{{ $task->name }}</div>
                                        <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $task->given_on->format('D j M') }}</div>
                                    </div>
                                    <span class="shrink-0 text-sm tabular-nums text-zinc-700 dark:text-zinc-300">{{ __('out of :max', ['max' => $task->max_mark]) }}</span>
                                    @if ($this->canManage)
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Task actions')" />
                                        <flux:menu>
                                            <flux:menu.item icon="pencil" wire:click="startEdit({{ $task->id }})">{{ __('Edit') }}</flux:menu.item>
                                            <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $task->id }})"
                                                wire:confirm="{{ __('Delete :task?', ['task' => $task->name]) }}">{{ __('Delete') }}</flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endforeach
        </div>

        <flux:modal name="task" class="w-full md:w-[30rem]">
            <form wire:submit="save" class="flex flex-col gap-4">
                <flux:heading size="lg">{{ $editingId ? __('Edit task') : __('Add a task') }}</flux:heading>

                <flux:select wire:model="componentId" :label="__('Part')">
                    @foreach ($scheme->components as $part)
                        <flux:select.option :value="(string) $part->id">{{ $part->name }} ({{ $part->weight }}%)</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="component" />

                <flux:input wire:model="name" :label="__('Name')" :placeholder="__('e.g. Class test 1')" />

                <div class="grid grid-cols-2 gap-3">
                    <flux:input wire:model="givenOn" type="date" :label="__('Date given')"
                        :min="$this->term->starts_on->toDateString()" :max="$this->term->ends_on->toDateString()" />
                    <flux:input wire:model="maxMark" type="number" min="1" max="{{ \App\Actions\Assessment\TaskRules::MAX_MARK }}" inputmode="numeric" :label="__('Out of')" />
                </div>

                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary" data-test="save-task">{{ $editingId ? __('Save') : __('Add task') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>
