<x-layouts::auth.simple :title="__('Account suspended')">
    <div class="flex flex-col gap-2 text-center">
        <flux:heading size="xl">{{ $school->name }}</flux:heading>
        <flux:text>{{ __('This school\'s account is currently suspended.') }}</flux:text>
        <flux:text>{{ __('Please contact the school office or Orange Tree support.') }}</flux:text>
    </div>
</x-layouts::auth.simple>
