<div class="flex max-w-4xl flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Discounts and scholarships') }}</flux:heading>
            <flux:text>{{ __('The sibling discount is worked out at billing: 2nd and later children who share a guardian, by admission date. Others are given on each student\'s page.') }}</flux:text>
        </div>
        @if ($this->canManage())
            <flux:button wire:click="startCreate" variant="primary" icon="plus" data-test="new-discount">{{ __('New discount') }}</flux:button>
        @endif
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Discount') }}</flux:table.column>
            <flux:table.column>{{ __('For') }}</flux:table.column>
            <flux:table.column>{{ __('Takes off') }}</flux:table.column>
            <flux:table.column>{{ __('Students') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->discounts as $discount)
                <flux:table.row :key="'discount-'.$discount->id" data-test="discount-row">
                    <flux:table.cell>
                        {{ $discount->name }}
                        @unless ($discount->is_active)
                            <flux:badge size="sm" color="zinc">{{ __('Not in use') }}</flux:badge>
                        @endunless
                    </flux:table.cell>
                    <flux:table.cell>{{ $discount->kind->label() }}</flux:table.cell>
                    <flux:table.cell>{{ $discount->describe($this->itemNames) }}</flux:table.cell>
                    <flux:table.cell>{{ $discount->kind->isAutomatic() ? __('Automatic') : ($this->studentCounts[$discount->id] ?? 0) }}</flux:table.cell>
                    <flux:table.cell class="text-end">
                        @if ($this->canManage())
                            <flux:button size="sm" variant="ghost" icon="pencil" wire:click="startEdit({{ $discount->id }})" />
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('No discounts yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div>
        <flux:heading size="lg">{{ __('Scholarships') }}</flux:heading>
        <flux:text class="mb-2">{{ __('Given and changed on each student\'s page.') }}</flux:text>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Student') }}</flux:table.column>
                <flux:table.column>{{ __('Scholarship') }}</flux:table.column>
                <flux:table.column>{{ __('Takes off') }}</flux:table.column>
                <flux:table.column>{{ __('Until') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->scholarships as $scholarship)
                    <flux:table.row :key="'scholarship-'.$scholarship->id" data-test="scholarship-row">
                        <flux:table.cell>
                            <flux:link :href="route('students.show', $scholarship->student)" wire:navigate>{{ $scholarship->student->full_name }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $scholarship->name }}
                            @if ($scholarship->sponsor)
                                <flux:text size="sm" class="inline">({{ $scholarship->sponsor }})</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $scholarship->describe($this->itemNames) }}</flux:table.cell>
                        <flux:table.cell>{{ $scholarship->endsTerm?->name ?? __('Until removed') }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">{{ __('No scholarships.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    @if ($this->canManage())
    <flux:modal name="discount" class="md:w-[30rem]">
        <form wire:submit="save" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $editingId ? __('Edit discount') : __('New discount') }}</flux:heading>
            <flux:input wire:model="form.name" :label="__('Name')" :placeholder="__('e.g. Staff child, Second child')" />
            <flux:error name="name" />
            <flux:select wire:model="form.kind" :label="__('For')">
                @foreach ($this->kinds() as $kind)
                    <flux:select.option :value="$kind->value">{{ $kind->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="kind" />
            @include('livewire.fees.partials.reduction-fields', ['itemNames' => $this->itemNames, 'types' => $this->types()])
            @if ($editingId)
                <flux:checkbox wire:model="form.is_active" :label="__('In use')" />
            @endif
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
    @endif
</div>
