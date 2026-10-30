<div class="flex max-w-4xl flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('SHS programmes') }}</flux:heading>
            <flux:text>{{ __('Each programme has core subjects for everyone and elective options.') }}</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button :href="route('subjects.index')" wire:navigate variant="ghost" icon="arrow-left">{{ __('Subjects') }}</flux:button>
            <flux:button wire:click="generateDefaults" icon="sparkles" data-test="generate-programmes">{{ __('Add preset programmes') }}</flux:button>
        </div>
    </div>

    <form wire:submit="create" class="flex items-end gap-3">
        <flux:input wire:model="newName" :label="__('New programme')" placeholder="Technical" class="max-w-64" />
        <flux:button type="submit" variant="primary">{{ __('Add') }}</flux:button>
    </form>
    <flux:error name="programme" />

    @forelse ($this->programmes as $programme)
        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" wire:key="programme-{{ $programme->id }}">
            <div class="mb-2 flex items-center justify-between">
                <flux:heading size="lg" data-test="programme-name">{{ $programme->name }}</flux:heading>
                <div>
                    <flux:button size="sm" variant="ghost" icon="pencil" wire:click="startEdit({{ $programme->id }})">{{ __('Edit') }}</flux:button>
                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $programme->id }})"
                        wire:confirm="{{ __('Delete :programme?', ['programme' => $programme->name]) }}" />
                </div>
            </div>
            @foreach (\App\Enums\SubjectType::cases() as $type)
                <div class="flex flex-wrap items-center gap-1 py-1">
                    <flux:text class="w-20">{{ $type->label() }}</flux:text>
                    @foreach ($programme->subjects->filter(fn ($s) => $s->pivot->type === $type) as $subject)
                        <flux:badge size="sm" :color="$type === \App\Enums\SubjectType::Core ? 'zinc' : 'sky'">{{ $subject->name }}</flux:badge>
                    @endforeach
                </div>
            @endforeach
        </div>
    @empty
        <flux:callout icon="academic-cap">
            <flux:callout.heading>{{ __('No programmes yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('"Add preset programmes" creates General Science, Business, General Arts and others.') }}</flux:callout.text>
        </flux:callout>
    @endforelse

    <flux:modal name="programme" class="md:w-[36rem]">
        <form wire:submit="save" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Edit programme') }}</flux:heading>
            <flux:input wire:model="editName" :label="__('Name')" />
            <flux:error name="programme" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:checkbox.group wire:model="core" :label="__('Core')">
                    @foreach ($this->shsSubjects as $subject)
                        <flux:checkbox :value="(string) $subject->id" :label="$subject->name" />
                    @endforeach
                </flux:checkbox.group>
                <flux:checkbox.group wire:model="electives" :label="__('Electives')">
                    @foreach ($this->shsSubjects as $subject)
                        <flux:checkbox :value="(string) $subject->id" :label="$subject->name" />
                    @endforeach
                </flux:checkbox.group>
            </div>
            <flux:error name="subjects" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
