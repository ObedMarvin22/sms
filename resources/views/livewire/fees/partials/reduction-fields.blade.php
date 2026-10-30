{{-- Shared by discounts and scholarships: type, value and which fee items. Binds form.type, form.value, form.whole_bill, form.fee_item_ids. --}}
<flux:radio.group wire:model.live="form.type" variant="segmented" :label="__('Takes off')" size="sm">
    @foreach ($types as $type)
        <flux:radio :value="$type->value" :label="$type->label()" />
    @endforeach
</flux:radio.group>
<flux:error name="type" />
<flux:input wire:model="form.value" inputmode="decimal" class="max-w-40" data-test="reduction-value"
    :label="($form['type'] ?? '') === 'fixed' ? __('Amount (GH₵)') : __('Percentage (%)')" />
<flux:error name="value" />
<flux:checkbox wire:model.live="form.whole_bill" :label="__('Whole bill')" :description="__('Untick to choose the fee items it applies to.')" />
@unless ($form['whole_bill'] ?? true)
    <flux:checkbox.group wire:model="form.fee_item_ids" :label="__('Applies to')">
        @foreach ($itemNames as $id => $name)
            <flux:checkbox :value="(string) $id" :label="$name" />
        @endforeach
    </flux:checkbox.group>
@endunless
<flux:error name="fee_item_ids" />
