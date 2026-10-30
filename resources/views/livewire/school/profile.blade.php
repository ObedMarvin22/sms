<div class="flex max-w-2xl flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('School profile') }}</flux:heading>
        <flux:text>{{ __('How your school appears to staff and parents.') }}</flux:text>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6">
        <flux:input wire:model="name" :label="__('School name')" required />

        <flux:input :value="$subdomain.'.'.config('app.base_domain')" :label="__('Web address')" disabled
            :description="__('Contact Orange Tree to change it.')" />

        <div class="flex flex-col gap-3">
            <flux:label>{{ __('Logo') }}</flux:label>
            <div class="flex items-center gap-4">
                @if ($logo)
                    <img src="{{ $logo->temporaryUrl() }}" alt="" class="size-16 rounded-md object-contain" data-test="logo-preview">
                @elseif ($this->school()->logo_path)
                    <img src="{{ route('school.logo') }}?v={{ $this->school()->updated_at?->timestamp }}" alt="{{ __('Current logo') }}" class="size-16 rounded-md object-contain" data-test="logo-current">
                @endif
                <input type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp" class="text-sm" data-test="logo-input">
            </div>
            <flux:text size="sm">{{ __('PNG, JPG or WebP, up to 1 MB.') }}</flux:text>
            <flux:error name="logo" />
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <flux:input wire:model="primary_colour" :label="__('Primary colour')" placeholder="#0A7D3C" />
            <flux:input wire:model="secondary_colour" :label="__('Secondary colour')" placeholder="#F5D90A" />
        </div>

        <flux:separator />

        <div class="grid gap-6 sm:grid-cols-2">
            <flux:input wire:model="phone" :label="__('Phone')" type="tel" placeholder="024 412 3456" />
            <flux:input wire:model="email" :label="__('Email')" type="email" />
        </div>
        <flux:textarea wire:model="address" :label="__('Address')" rows="2" />

        <flux:checkbox.group wire:model="levels" :label="__('Levels offered')">
            @foreach ($this->levelOptions() as $level)
                <flux:checkbox :value="$level->value" :label="$level->label()" />
            @endforeach
        </flux:checkbox.group>

        <div>
            <flux:button type="submit" variant="primary" data-test="save-school-profile">{{ __('Save') }}</flux:button>
        </div>
    </form>
</div>
