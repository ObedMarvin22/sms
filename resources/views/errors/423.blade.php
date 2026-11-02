<x-layouts::auth :title="__('Read-only')">
    <div class="flex flex-col gap-4 text-center">
        <flux:heading size="xl">{{ __('This school is read-only') }}</flux:heading>
        <flux:text>{{ \App\Exceptions\SchoolIsReadOnly::MESSAGE }}</flux:text>
        <flux:button :href="url()->previous()" variant="primary">{{ __('Go back') }}</flux:button>
    </div>
</x-layouts::auth>
