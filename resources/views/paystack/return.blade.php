<x-layouts::auth :title="__('Payment')">
    <div class="flex flex-col gap-4 text-center" data-test="paystack-return">
        <flux:heading size="xl">{{ $school->name }}</flux:heading>

        @switch($transaction->status)
            @case(\App\Enums\PaystackTransactionStatus::Success)
                <flux:callout variant="success" icon="check-circle">
                    <flux:callout.heading>{{ __('Payment received') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __(':amount for :name. Receipt :number.', [
                            'amount' => $transaction->amount->format(),
                            'name' => $transaction->student->full_name,
                            'number' => $transaction->payment?->receipt_number,
                        ]) }}
                    </flux:callout.text>
                </flux:callout>
                @break
            @case(\App\Enums\PaystackTransactionStatus::Failed)
                <flux:callout variant="danger" icon="x-circle">
                    <flux:callout.heading>{{ __('The payment did not go through') }}</flux:callout.heading>
                    <flux:callout.text>{{ __('No money was taken. You can try again.') }}</flux:callout.text>
                </flux:callout>
                @break
            @default
                <flux:callout icon="clock">
                    <flux:callout.heading>{{ __('We are confirming your payment') }}</flux:callout.heading>
                    <flux:callout.text>{{ __('This can take a few minutes. The school will send a receipt once it is confirmed; you do not need to pay again.') }}</flux:callout.text>
                </flux:callout>
        @endswitch

        <flux:text size="sm">{{ __('Reference') }}: <span class="font-mono">{{ $transaction->reference }}</span></flux:text>
    </div>
</x-layouts::auth>
