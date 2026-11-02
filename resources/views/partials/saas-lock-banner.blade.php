@php($from = config('saas.invoice'))
@php($contact = collect([$from['from_phone'] ?? null, $from['from_email'] ?? null])->filter()->implode(' · '))
<div class="mb-6" data-test="saas-lock-banner">
    @if ($lock->isLocked())
        <flux:callout variant="danger" icon="lock-closed">
            <flux:callout.heading>{{ __('This school is read-only') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Orange Tree invoice :number (:balance) is unpaid. You can still view everything, download reports and record fee payments, but nothing else can be changed until it is paid.', [
                    'number' => $lock->invoice?->number,
                    'balance' => $lock->invoice?->balance()->format(),
                ]) }}
                @if ($contact !== '')
                    {{ __('Contact Orange Tree: :contact.', ['contact' => $contact]) }}
                @endif
            </flux:callout.text>
        </flux:callout>
    @else
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.heading>{{ __('Orange Tree invoice :number is overdue', ['number' => $lock->invoice?->number]) }}</flux:callout.heading>
            <flux:callout.text>
                {{ __(':balance was due on :due. Staff screens become read-only on :date unless it is paid.', [
                    'balance' => $lock->invoice?->balance()->format(),
                    'due' => $lock->invoice?->due_on->format('j M Y'),
                    'date' => $lock->locksOn?->format('j M Y'),
                ]) }}
                @if ($contact !== '')
                    {{ __('Contact Orange Tree: :contact.', ['contact' => $contact]) }}
                @endif
            </flux:callout.text>
        </flux:callout>
    @endif
</div>
