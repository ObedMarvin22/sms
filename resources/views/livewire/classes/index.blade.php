<div class="flex max-w-4xl flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Classes') }}</flux:heading>
            <flux:text>{{ __('Classes and their streams, by level. Students are enrolled into a stream.') }}</flux:text>
        </div>
        <flux:button wire:click="generateDefaults" icon="sparkles" data-test="generate-defaults">{{ __('Generate default classes') }}</flux:button>
    </div>

    <form wire:submit="createClass" class="flex flex-wrap items-end gap-3">
        <flux:select wire:model="newLevel" :label="__('Level')" class="max-w-40">
            @foreach ($this->levels() as $level)
                <flux:select.option :value="$level->value">{{ $level->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:input wire:model="newName" :label="__('New class')" placeholder="Primary 7" class="max-w-56" />
        <flux:button type="submit" variant="primary" data-test="add-class">{{ __('Add class') }}</flux:button>
    </form>
    <flux:error name="new" />

    @forelse ($this->classes->groupBy(fn ($class) => $class->level->value) as $levelValue => $classes)
        <div wire:key="level-{{ $levelValue }}">
            <flux:heading size="lg" class="mb-2">{{ \App\Enums\SchoolLevel::from($levelValue)->label() }}</flux:heading>

            <div class="flex flex-col divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                @foreach ($classes as $class)
                    <div class="flex flex-wrap items-center gap-3 p-3" wire:key="class-{{ $class->id }}">
                        <span class="w-32 font-medium" data-test="class-name">{{ $class->name }}</span>

                        <div class="flex flex-wrap items-center gap-1">
                            @foreach ($class->streams as $stream)
                                <flux:badge size="sm" wire:key="stream-{{ $stream->id }}">
                                    {{ $class->name }}{{ $stream->name }}
                                    <flux:badge.close wire:click="deleteStream({{ $stream->id }})" />
                                </flux:badge>
                                <flux:button size="xs" variant="ghost" icon="pencil" wire:click="startRename('stream', {{ $stream->id }})" :aria-label="__('Rename stream')" />
                            @endforeach
                        </div>

                        <form wire:submit="addStream({{ $class->id }})" class="ms-auto flex items-center gap-2">
                            <flux:input size="sm" wire:model="newStream.{{ $class->id }}" placeholder="B" class="w-16" />
                            <flux:button size="sm" type="submit">{{ __('Add stream') }}</flux:button>
                        </form>
                        <flux:button size="sm" variant="ghost" icon="pencil" wire:click="startRename('class', {{ $class->id }})">{{ __('Rename') }}</flux:button>
                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="deleteClass({{ $class->id }})"
                            wire:confirm="{{ __('Delete :class and its streams?', ['class' => $class->name]) }}" />
                        <flux:error name="stream.{{ $class->id }}" class="w-full" />
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <flux:callout icon="rectangle-group">
            <flux:callout.heading>{{ __('No classes yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Use "Generate default classes" to create the usual classes for your levels, then adjust them.') }}</flux:callout.text>
        </flux:callout>
    @endforelse

    <flux:modal name="rename" class="md:w-96">
        <form wire:submit="saveRename" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $renameType === 'stream' ? __('Rename stream') : __('Rename class') }}</flux:heading>
            <flux:input wire:model="renameValue" :label="__('Name')" />
            <flux:error name="rename" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
