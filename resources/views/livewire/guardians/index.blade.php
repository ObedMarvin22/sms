<div class="flex max-w-5xl flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Guardians') }}</flux:heading>
        <flux:text>{{ __('Add a guardian from the child\'s page. One record per phone number, shared by brothers and sisters.') }}</flux:text>
    </div>

    <flux:input wire:model.live.debounce.300ms="search" :label="__('Search')" icon="magnifying-glass" placeholder="Name or phone" class="max-w-80" />

    <flux:table :paginate="$this->guardians">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Phone') }}</flux:table.column>
            <flux:table.column>{{ __('Children') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->guardians as $guardian)
                <flux:table.row :key="'guardian-'.$guardian->id" data-test="guardian-row">
                    <flux:table.cell>{{ $guardian->name }}</flux:table.cell>
                    <flux:table.cell class="font-mono text-sm">{{ $guardian->phone }}</flux:table.cell>
                    <flux:table.cell>
                        @foreach ($guardian->links as $link)
                            <flux:link :href="route('students.show', $link->student_id)" wire:navigate>{{ $link->student->full_name }}</flux:link>
                            <flux:text size="sm" class="inline">({{ $link->relationship->label() }})</flux:text>@if (! $loop->last), @endif
                        @endforeach
                    </flux:table.cell>
                    <flux:table.cell class="text-end">
                        @if ($this->canManage())
                            <flux:button size="sm" variant="ghost" icon="pencil" wire:click="startEdit({{ $guardian->id }})" />
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4">{{ __('No guardians found.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @if ($this->canManage())
        <flux:modal name="guardian-details" class="md:w-[36rem]">
            <form wire:submit="save" class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('Edit guardian') }}</flux:heading>
                <flux:input wire:model="form.name" :label="__('Full name')" />
                <flux:error name="name" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="form.phone" :label="__('Phone')" />
                    <flux:input wire:model="form.alt_phone" :label="__('Other phone')" />
                </div>
                <flux:error name="phone" /><flux:error name="alt_phone" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input type="email" wire:model="form.email" :label="__('Email')" />
                    <flux:input wire:model="form.occupation" :label="__('Occupation')" />
                </div>
                <flux:error name="email" />
                <flux:input wire:model="form.address" :label="__('Address')" />
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>
