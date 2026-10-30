{{-- Shown while the signed-in user's role needs two-factor and it is not set up (EnsureTwoFactorEnabled). --}}
@auth
    @if (auth()->user()->requiresTwoFactor() && ! auth()->user()->hasConfirmedTwoFactor())
        <flux:callout variant="warning" icon="shield-exclamation" {{ $attributes->merge(['data-test' => 'two-factor-required']) }}>
            <flux:callout.heading>{{ __('Two-factor authentication required') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Your role requires two-factor authentication. Please set it up to continue.') }}</flux:callout.text>
        </flux:callout>
    @endif
@endauth
