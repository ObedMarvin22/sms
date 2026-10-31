<div class="flex max-w-6xl flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Invoices') }}</flux:heading>
        <flux:text>{{ __('Issued by the nightly census once a term\'s census date passes. Payments are recorded on each school\'s Billing page.') }}</flux:text>
    </div>

    @php($totals = $this->totals())
    <div class="grid gap-3 sm:grid-cols-3">
        <div><flux:text size="sm">{{ __('Owed by schools') }}</flux:text><div class="text-xl" data-test="owed">{{ $totals['owed']->format() }}</div></div>
        <div><flux:text size="sm">{{ __('Of which overdue') }}</flux:text><div class="text-xl text-red-600" data-test="overdue">{{ $totals['overdue']->format() }}</div></div>
    </div>

    <flux:select wire:model.live="statusFilter" :label="__('Status')" class="max-w-60">
        <flux:select.option value="">{{ __('All') }}</flux:select.option>
        @foreach (\App\Enums\SaasInvoiceStatus::cases() as $status)
            <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
        @endforeach
    </flux:select>

    <flux:table :paginate="$this->invoices">
        <flux:table.columns>
            <flux:table.column>{{ __('Invoice') }}</flux:table.column>
            <flux:table.column>{{ __('School') }}</flux:table.column>
            <flux:table.column>{{ __('Term') }}</flux:table.column>
            <flux:table.column>{{ __('Amount') }}</flux:table.column>
            <flux:table.column>{{ __('Balance') }}</flux:table.column>
            <flux:table.column>{{ __('Due') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->invoices as $invoice)
                @php($status = $invoice->status())
                <flux:table.row :key="'invoice-'.$invoice->id" data-test="invoice-row">
                    <flux:table.cell class="font-mono text-sm">
                        <flux:link :href="route('console.invoices.pdf', $invoice)" target="_blank">{{ $invoice->number }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:link :href="route('console.schools.billing', $invoice->school)" wire:navigate>{{ $invoice->school->name }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ $invoice->term->name }}</flux:table.cell>
                    <flux:table.cell>{{ $invoice->amount->format() }}</flux:table.cell>
                    <flux:table.cell>{{ $status === \App\Enums\SaasInvoiceStatus::Void ? '—' : $invoice->balance()->format() }}</flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">{{ $invoice->due_on->format('j M Y') }}</flux:table.cell>
                    <flux:table.cell><flux:badge size="sm" :color="$status->colour()">{{ $status->label() }}</flux:badge></flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7">{{ __('No invoices.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
