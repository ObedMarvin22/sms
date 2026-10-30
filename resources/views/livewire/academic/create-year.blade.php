<div class="flex max-w-3xl flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('New academic year') }}</flux:heading>
        <flux:text>{{ __('Dates are prefilled with a typical calendar. Adjust them to your school.') }}</flux:text>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6">
        <div class="grid gap-4 sm:grid-cols-3">
            <flux:input wire:model="name" :label="__('Name')" placeholder="2026/2027" />
            <flux:input type="date" wire:model="starts_on" :label="__('Year starts')" />
            <flux:input type="date" wire:model="ends_on" :label="__('Year ends')" />
        </div>
        <flux:error name="terms" />

        @foreach ($terms as $i => $term)
            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" wire:key="term-{{ $i }}">
                <flux:heading class="mb-3">{{ $this->periodName($i + 1) }}</flux:heading>
                <div class="grid gap-4 sm:grid-cols-3">
                    <flux:input type="date" wire:model="terms.{{ $i }}.starts_on" :label="__('Starts')" />
                    <flux:input type="date" wire:model="terms.{{ $i }}.ends_on" :label="__('Ends')" />
                    <flux:input type="date" wire:model="terms.{{ $i }}.census_on" :label="__('Census date')" />
                </div>
            </div>
        @endforeach

        <flux:text size="sm">{{ __('The census date is when active students are counted for billing.') }}</flux:text>

        <div class="flex gap-3">
            <flux:button type="submit" variant="primary" data-test="create-year-button">{{ __('Create academic year') }}</flux:button>
            <flux:button :href="route('academic-years.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>
