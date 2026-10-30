<div class="flex max-w-4xl flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Fee items') }}</flux:heading>
            <flux:text>{{ __('What the school charges for. Set the amounts for each class and term under Fee structures.') }}</flux:text>
        </div>
        @if ($this->canManage())
            <div class="flex gap-2">
                <flux:button :href="route('fees.structures')" icon="table-cells" wire:navigate>{{ __('Fee structures') }}</flux:button>
                <flux:button wire:click="startCreate" variant="primary" icon="plus" data-test="new-fee-item">{{ __('New fee item') }}</flux:button>
            </div>
        @endif
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Category') }}</flux:table.column>
            <flux:table.column>{{ __('Charged to') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->items as $item)
                <flux:table.row :key="'fee-item-'.$item->id" data-test="fee-item-row">
                    <flux:table.cell>
                        {{ $item->name }}
                        @unless ($item->is_active)
                            <flux:badge size="sm" color="zinc">{{ __('Archived') }}</flux:badge>
                        @endunless
                    </flux:table.cell>
                    <flux:table.cell>{{ $item->category->label() }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($item->is_optional)
                            <flux:badge size="sm" color="sky">{{ __('Students who opt in') }}</flux:badge>
                        @else
                            {{ __('Everyone in the class') }}
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="text-end">
                        @if ($this->canManage())
                            <flux:button size="sm" variant="ghost" icon="pencil" wire:click="startEdit({{ $item->id }})" />
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4">{{ __('No fee items yet. Start with tuition, then levies such as PTA dues.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @if ($this->canManage())
    <flux:modal name="fee-item" class="md:w-[28rem]">
        <form wire:submit="save" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $editingId ? __('Edit fee item') : __('New fee item') }}</flux:heading>
            <flux:input wire:model="form.name" :label="__('Name')" :placeholder="__('e.g. Tuition, PTA dues, Bus (Spintex route)')" />
            <flux:error name="name" />
            <flux:select wire:model="form.category" :label="__('Category')">
                @foreach ($this->categories() as $category)
                    <flux:select.option :value="$category->value">{{ $category->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="category" />
            <flux:checkbox wire:model="form.is_optional" :label="__('Optional')"
                :description="__('Only students who opt in pay it (transport, feeding, extra classes).')" />
            <flux:error name="is_optional" />
            @if ($editingId)
                <flux:checkbox wire:model="form.is_active" :label="__('In use')"
                    :description="__('Untick to archive: it stays on existing structures but cannot be added to new ones.')" />
            @endif
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
    @endif
</div>
