<div class="flex flex-col gap-6">
    <x-auth-header :title="__('Orange Tree console')" :description="__('Staff sign-in')" />

    <form wire:submit="login" class="flex flex-col gap-6">
        <flux:input wire:model="email" :label="__('Email address')" type="email" required autofocus autocomplete="email" />
        <flux:input wire:model="password" :label="__('Password')" type="password" required autocomplete="current-password" viewable />

        <flux:button type="submit" variant="primary" class="w-full" data-test="console-login-button">
            {{ __('Log in') }}
        </flux:button>
    </form>
</div>
