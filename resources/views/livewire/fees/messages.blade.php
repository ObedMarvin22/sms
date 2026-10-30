<div class="flex max-w-5xl flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Messages') }}</flux:heading>
        <flux:text>{{ __('SMS sent to parents. Which notices go by SMS is chosen on School profile; Orange Tree adds credit.') }}</flux:text>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <div><flux:text size="sm">{{ __('Units left') }}</flux:text><div class="text-xl" data-test="balance">{{ number_format($this->balance()) }}</div></div>
        <div><flux:text size="sm">{{ __('Used this term') }}</flux:text><div class="text-xl">{{ number_format($this->usedThisTerm()) }}</div></div>
    </div>

    <flux:select wire:model.live="statusFilter" :label="__('Status')" class="max-w-60">
        <flux:select.option value="">{{ __('All') }}</flux:select.option>
        @foreach (\App\Enums\SmsStatus::cases() as $status)
            <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
        @endforeach
    </flux:select>

    <flux:table :paginate="$this->messages">
        <flux:table.columns>
            <flux:table.column>{{ __('When') }}</flux:table.column>
            <flux:table.column>{{ __('To') }}</flux:table.column>
            <flux:table.column>{{ __('Message') }}</flux:table.column>
            <flux:table.column>{{ __('Units') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->messages as $sms)
                <flux:table.row :key="'sms-'.$sms->id" data-test="sms-row">
                    <flux:table.cell class="whitespace-nowrap">{{ $sms->created_at->format('j M H:i') }}</flux:table.cell>
                    <flux:table.cell class="font-mono text-sm">{{ $sms->to }}</flux:table.cell>
                    <flux:table.cell class="whitespace-normal text-sm">{{ $sms->message }}</flux:table.cell>
                    <flux:table.cell>{{ $sms->segments }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="match ($sms->status) { \App\Enums\SmsStatus::Sent => 'green', \App\Enums\SmsStatus::Queued => 'zinc', default => 'red' }" :title="$sms->error">
                            {{ $sms->status->label() }}
                        </flux:badge>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('No SMS yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
