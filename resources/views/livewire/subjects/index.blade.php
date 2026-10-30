<div class="flex max-w-4xl flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Subjects') }}</flux:heading>
            <flux:text>{{ __('One subject can be taught at several levels. Step 6 decides which classes take it.') }}</flux:text>
        </div>
        <div class="flex gap-2">
            @if ($this->offersShs())
                <flux:button :href="route('programmes.index')" wire:navigate icon="academic-cap" data-test="programmes-link">{{ __('SHS programmes') }}</flux:button>
            @endif
            <flux:button wire:click="generateDefaults" icon="sparkles" data-test="generate-subjects">{{ __('Add preset subjects') }}</flux:button>
            <flux:button wire:click="startCreate" variant="primary" icon="plus" data-test="new-subject">{{ __('New subject') }}</flux:button>
        </div>
    </div>

    <div class="max-w-xs">
        <flux:select wire:model.live="level" :label="__('Level')">
            <flux:select.option value="">{{ __('All levels') }}</flux:select.option>
            @foreach ($this->offeredLevels() as $option)
                <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Subject') }}</flux:table.column>
            <flux:table.column>{{ __('Code') }}</flux:table.column>
            <flux:table.column>{{ __('Levels') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->subjects as $subject)
                <flux:table.row :key="'subject-'.$subject->id">
                    <flux:table.cell data-test="subject-name">{{ $subject->name }}</flux:table.cell>
                    <flux:table.cell>{{ $subject->code }}</flux:table.cell>
                    <flux:table.cell>
                        @foreach ($subject->levels as $subjectLevel)
                            <flux:badge size="sm">{{ $subjectLevel->label() }}</flux:badge>
                        @endforeach
                    </flux:table.cell>
                    <flux:table.cell class="text-end">
                        <flux:button size="sm" variant="ghost" icon="pencil" wire:click="startEdit({{ $subject->id }})" />
                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $subject->id }})"
                            wire:confirm="{{ __('Delete :subject?', ['subject' => $subject->name]) }}" />
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4">{{ __('No subjects yet. "Add preset subjects" creates the usual ones for your levels.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="subject" class="md:w-[28rem]">
        <form wire:submit="save" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $editingId ? __('Edit subject') : __('New subject') }}</flux:heading>
            <flux:input wire:model="name" :label="__('Name')" />
            <flux:input wire:model="code" :label="__('Code')" :description="__('Optional, e.g. ENG.')" class="max-w-40" />
            <flux:checkbox.group wire:model="levels" :label="__('Taught at')">
                @foreach ($this->offeredLevels() as $option)
                    <flux:checkbox :value="$option->value" :label="$option->label()" />
                @endforeach
            </flux:checkbox.group>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
