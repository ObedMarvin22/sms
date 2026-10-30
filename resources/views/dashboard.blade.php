<x-layouts::app :title="__('Dashboard')">
    {{-- Placeholder until the real dashboard: who you are, here. --}}
    <div class="flex max-w-2xl flex-col gap-4">
        <flux:heading size="xl">{{ $currentSchool->name }}</flux:heading>

        <flux:text>{{ __('Welcome, :name.', ['name' => auth()->user()->name]) }}</flux:text>

        @php($roles = auth()->user()->getRoleNames()->map(fn ($name) => \App\Enums\RoleKey::tryFrom($name)?->label() ?? $name))
        <div class="flex flex-wrap gap-2" data-test="dashboard-roles">
            @forelse ($roles as $role)
                <flux:badge color="zinc">{{ $role }}</flux:badge>
            @empty
                <flux:text>{{ __('You have no role at this school yet. Ask an administrator.') }}</flux:text>
            @endforelse
        </div>
    </div>
</x-layouts::app>
