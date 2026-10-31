<div class="flex max-w-3xl flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Plans') }}</flux:heading>
            <flux:text>{{ __('What a school pays per active student per term. Changing a rate only affects invoices issued afterwards.') }}</flux:text>
        </div>
        @if ($this->canManage())
            <flux:button wire:click="startCreate" variant="primary" icon="plus" data-test="new-plan">{{ __('New plan') }}</flux:button>
        @endif
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Plan') }}</flux:table.column>
            <flux:table.column>{{ __('Per student per term') }}</flux:table.column>
            <flux:table.column>{{ __('Schools') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->plans as $plan)
                <flux:table.row :key="'plan-'.$plan->id" data-test="plan-row">
                    <flux:table.cell>
                        {{ $plan->name }}
                        @unless ($plan->is_active)
                            <flux:badge size="sm" color="zinc">{{ __('Not offered') }}</flux:badge>
                        @endunless
                        @if ($plan->description)
                            <flux:text size="sm">{{ $plan->description }}</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $plan->rate_per_student->format() }}</flux:table.cell>
                    <flux:table.cell>{{ $plan->schools_count }}</flux:table.cell>
                    <flux:table.cell class="text-end">
                        @if ($this->canManage())
                            <flux:button size="sm" variant="ghost" icon="pencil" wire:click="startEdit({{ $plan->id }})" />
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4">{{ __('No plans yet. Add one before billing schools.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @if ($this->canManage())
    <flux:modal name="plan" class="md:w-[28rem]">
        <form wire:submit="save" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $editingId ? __('Edit plan') : __('New plan') }}</flux:heading>
            <flux:input wire:model="form.name" :label="__('Name')" :placeholder="__('e.g. Standard')" />
            <flux:error name="name" />
            <flux:input wire:model="form.rate" :label="__('Per student per term (GH₵)')" inputmode="decimal" :placeholder="__('e.g. 15.00')" />
            <flux:error name="rate" />
            <flux:input wire:model="form.description" :label="__('Description (optional)')" />
            @if ($editingId)
                <flux:checkbox wire:model="form.is_active" :label="__('Offered to schools')" />
            @endif
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
    @endif
</div>
