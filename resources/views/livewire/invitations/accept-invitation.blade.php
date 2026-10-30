<div class="flex flex-col gap-6">
    <x-auth-header :title="__('Set your password')" :description="__('Choose a password to finish setting up your account')" />

    <form wire:submit="save" class="flex flex-col gap-6">
        <flux:input
            wire:model="password"
            :label="__('Password')"
            type="password"
            required
            autocomplete="new-password"
            passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
            viewable
        />

        <flux:input
            wire:model="password_confirmation"
            :label="__('Confirm password')"
            type="password"
            required
            autocomplete="new-password"
            viewable
        />

        <flux:button type="submit" variant="primary" class="w-full" data-test="accept-invitation-button">
            {{ __('Set password and sign in') }}
        </flux:button>
    </form>
</div>
