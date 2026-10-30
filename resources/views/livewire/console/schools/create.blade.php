<div class="flex max-w-xl flex-col gap-6">
    <flux:heading size="xl">{{ __('Create school') }}</flux:heading>

    <form wire:submit="save" class="flex flex-col gap-6">
        <flux:input wire:model="name" :label="__('School name')" required />

        <flux:input wire:model="subdomain" :label="__('Subdomain')" required
            :description="__('Lowercase letters, numbers and hyphens. The school will be at subdomain.:domain', ['domain' => config('app.base_domain')])" />

        <flux:checkbox.group wire:model="levels" :label="__('Levels offered')">
            @foreach ($this->levelOptions() as $level)
                <flux:checkbox :value="$level->value" :label="$level->label()" />
            @endforeach
        </flux:checkbox.group>

        <flux:separator />

        <flux:heading>{{ __('First administrator') }}</flux:heading>
        <flux:input wire:model="adminName" :label="__('Name')" required />
        <flux:input wire:model="adminEmail" :label="__('Email')" type="email" required
            :description="__('They will receive an invitation to set their password.')" />

        <div class="flex gap-3">
            <flux:button type="submit" variant="primary" data-test="create-school-button">{{ __('Create school') }}</flux:button>
            <flux:button :href="route('console.schools.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>
