<div class="flex max-w-2xl flex-col gap-6">
    @php($school = $this->school())
    <div>
        <flux:heading size="xl">{{ __('SMS: :name', ['name' => $school->name]) }}</flux:heading>
        <flux:text>{{ __('One unit is one SMS of up to 160 characters. Units are taken when a message is queued and given back if it fails.') }}</flux:text>
    </div>

    <div>
        <flux:text size="sm">{{ __('Units left') }}</flux:text>
        <div class="text-2xl" data-test="balance">{{ number_format($this->balance()) }}</div>
    </div>

    <form wire:submit="saveSender" class="flex flex-col gap-3">
        <flux:heading>{{ __('Sender ID') }}</flux:heading>
        <flux:input wire:model="senderId" :label="__('Name on the SMS')" maxlength="11" class="max-w-60"
            :description="__('Only once Arkesel has approved it. Empty uses :default.', ['default' => config('sms.default_sender')])" />
        <flux:error name="sender_id" />
        <div><flux:button type="submit">{{ __('Save sender ID') }}</flux:button></div>
    </form>

    <form wire:submit="topUp" class="flex flex-col gap-3">
        <flux:heading>{{ __('Add credit') }}</flux:heading>
        <div class="grid gap-3 sm:grid-cols-[10rem_1fr]">
            <flux:input wire:model="units" :label="__('Units')" inputmode="numeric" data-test="units" />
            <flux:input wire:model="note" :label="__('How it was paid')" :placeholder="__('e.g. MoMo 0244 123456, ref 98765')" data-test="note" />
        </div>
        <flux:error name="units" />
        <flux:error name="note" />
        <div><flux:button type="submit" variant="primary" data-test="top-up">{{ __('Add units') }}</flux:button></div>
    </form>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('When') }}</flux:table.column>
            <flux:table.column>{{ __('Entry') }}</flux:table.column>
            <flux:table.column>{{ __('Units') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->history() as $entry)
                <flux:table.row :key="'entry-'.$entry->id">
                    <flux:table.cell>{{ $entry->created_at->format('j M Y H:i') }}</flux:table.cell>
                    <flux:table.cell>{{ $entry->type->label() }}@if ($entry->note): {{ $entry->note }}@endif</flux:table.cell>
                    <flux:table.cell class="font-mono">{{ $entry->units > 0 ? '+' : '' }}{{ $entry->units }}</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
