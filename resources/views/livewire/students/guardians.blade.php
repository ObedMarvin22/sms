<div>
    <div class="mb-2 flex items-center justify-between gap-4">
        <flux:heading size="lg">{{ __('Guardians') }}</flux:heading>
        @if ($this->canManage())
            <flux:button size="sm" icon="plus" wire:click="startAdd" data-test="add-guardian">{{ __('Add guardian') }}</flux:button>
        @endif
    </div>

    <flux:error name="guardian" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Relationship') }}</flux:table.column>
            <flux:table.column>{{ __('Phone') }}</flux:table.column>
            <flux:table.column>{{ __('Parent app') }}</flux:table.column>
            <flux:table.column>{{ __('Pickup') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->links() as $link)
                <flux:table.row :key="'guardian-link-'.$link->id" data-test="guardian-row">
                    <flux:table.cell>
                        {{ $link->guardian->name }}
                        @if ($link->is_primary)<flux:badge size="sm" color="green" class="ms-1">{{ __('Primary') }}</flux:badge>@endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $link->relationship->label() }}</flux:table.cell>
                    <flux:table.cell class="font-mono text-sm">{{ $link->guardian->phone }}</flux:table.cell>
                    <flux:table.cell>{{ $link->access_level->label() }}</flux:table.cell>
                    <flux:table.cell>{{ $link->can_pick_up ? __('Yes') : __('No') }}</flux:table.cell>
                    <flux:table.cell class="text-end">
                        @if ($this->canManage())
                            <flux:dropdown>
                                <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil" wire:click="startEdit({{ $link->id }})">{{ __('Edit') }}</flux:menu.item>
                                    @unless ($link->is_primary)
                                        <flux:menu.item icon="star" wire:click="makePrimary({{ $link->id }})">{{ __('Make primary contact') }}</flux:menu.item>
                                    @endunless
                                    <flux:menu.item icon="x-mark" variant="danger" wire:click="unlink({{ $link->id }})"
                                        wire:confirm="{{ __('Remove :name from this child? Their record stays for any other children.', ['name' => $link->guardian->name]) }}">{{ __('Remove from this child') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">{{ __('No guardians yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @if ($this->canManage())
        <flux:modal name="guardian" class="md:w-[36rem]">
            <form wire:submit="save" class="flex flex-col gap-4">
                <flux:heading size="lg">{{ $editingLinkId ? __('Edit guardian') : __('Add guardian') }}</flux:heading>

                <flux:input wire:model.live.debounce.400ms="form.phone" :label="__('Phone')" placeholder="024 412 3456" data-test="guardian-phone" />
                <flux:error name="phone" />

                @if ($match = $this->match())
                    <flux:callout icon="link" variant="secondary" data-test="guardian-match">
                        <flux:callout.heading>{{ __(':name already uses this number', ['name' => $match->name]) }}</flux:callout.heading>
                        <flux:callout.text>{{ __('They will be linked to this child. Their details are shared by all their children.') }}</flux:callout.text>
                    </flux:callout>
                @else
                    <flux:input wire:model="form.name" :label="__('Full name')" />
                    <flux:error name="name" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="form.alt_phone" :label="__('Other phone')" />
                        <flux:input type="email" wire:model="form.email" :label="__('Email')" />
                    </div>
                    <flux:error name="alt_phone" /><flux:error name="email" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="form.occupation" :label="__('Occupation')" />
                        <flux:input wire:model="form.address" :label="__('Address')" />
                    </div>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model="form.relationship" :label="__('Relationship')">
                        <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                        @foreach ($this->relationships() as $relationship)
                            <flux:select.option :value="$relationship->value">{{ $relationship->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="form.access_level" :label="__('Parent app access')">
                        @foreach ($this->accessLevels() as $access)
                            <flux:select.option :value="$access->value">{{ $access->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <flux:error name="relationship" /><flux:error name="access_level" />

                <flux:checkbox wire:model="form.can_pick_up" :label="__('May collect this child from school')" />

                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ $this->match() ? __('Link guardian') : __('Save') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>
