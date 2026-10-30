<div class="flex max-w-xl flex-col gap-6">
    @php($school = $this->school())
    <div>
        <flux:heading size="xl">{{ __('Online payments: :name', ['name' => $school->name]) }}</flux:heading>
        <flux:text>{{ __('Parents pay by card or mobile money through Paystack. The money settles straight to the school\'s own account; Orange Tree takes no share.') }}</flux:text>
    </div>

    @if ($school->paystack_subaccount_code)
        <flux:callout variant="success" icon="check-circle" data-test="connected">
            <flux:callout.heading>{{ __('Connected') }}</flux:callout.heading>
            <flux:callout.text>
                {{ $school->paystack_settlement }}<br>
                <span class="font-mono text-sm">{{ $school->paystack_subaccount_code }}</span>
            </flux:callout.text>
        </flux:callout>
        <flux:text size="sm">{{ __('To change the account, update the subaccount in the Paystack dashboard; changing it here comes later.') }}</flux:text>
    @else
        <form wire:submit="connect" class="flex flex-col gap-4">
            <flux:select wire:model="bankCode" :label="__('Bank or mobile money provider')" data-test="bank">
                <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                @foreach ($this->banks() as $bank)
                    <flux:select.option :value="$bank['code']">{{ $bank['name'] }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="bank_code" />
            <flux:input wire:model="accountNumber" :label="__('Account or mobile money number')" inputmode="numeric" data-test="account-number" />
            <flux:error name="account_number" />
            <flux:text size="sm">{{ __('Paystack checks the account name with the bank before connecting.') }}</flux:text>
            <div class="flex gap-3">
                <flux:button type="submit" variant="primary" data-test="connect">{{ __('Connect to Paystack') }}</flux:button>
                <flux:button :href="route('console.schools.index')" variant="ghost" wire:navigate>{{ __('Back') }}</flux:button>
            </div>
        </form>
    @endif
</div>
