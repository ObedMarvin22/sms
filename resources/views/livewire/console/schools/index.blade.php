<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Schools') }}</flux:heading>

        @can('create-schools')
            <flux:button :href="route('console.schools.create')" variant="primary" icon="plus" wire:navigate data-test="create-school-link">
                {{ __('Create school') }}
            </flux:button>
        @endcan
    </div>

    <flux:table :paginate="$this->schools">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Subdomain') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Created') }}</flux:table.column>
            <flux:table.column>{{ __('Payments and SMS') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->schools as $school)
                <flux:table.row :key="$school->id">
                    <flux:table.cell>{{ $school->name }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:link :href="route('school.home', ['school' => $school->subdomain])" target="_blank">{{ $school->subdomain }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell><flux:badge size="sm">{{ $school->status->label() }}</flux:badge></flux:table.cell>
                    <flux:table.cell>{{ $school->created_at?->toDateString() }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:link :href="route('console.schools.payments', $school)" wire:navigate data-test="payments-link">
                            {{ $school->paystack_subaccount_code ? __('Connected') : __('Set up') }}
                        </flux:link>
                        ·
                        <flux:link :href="route('console.schools.sms', $school)" wire:navigate data-test="sms-link">{{ __('SMS') }}</flux:link>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('No schools yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
