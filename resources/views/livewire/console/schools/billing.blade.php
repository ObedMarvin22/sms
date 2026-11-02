<div class="flex max-w-5xl flex-col gap-6">
    @php($school = $this->school())
    <div>
        <flux:heading size="xl">{{ __('Billing: :name', ['name' => $school->name]) }}</flux:heading>
        <flux:text>{{ __('Orange Tree invoices this school per active student on each term\'s census date. Status: :status.', ['status' => $school->status->label()]) }}</flux:text>
    </div>

    <form wire:submit="savePlan" class="flex flex-wrap items-end gap-3">
        <flux:select wire:model="planId" :label="__('Plan')" class="max-w-72" :disabled="! $this->canManage()" data-test="plan">
            <flux:select.option value="">{{ __('No plan yet') }}</flux:select.option>
            @foreach ($this->plans() as $plan)
                <flux:select.option :value="(string) $plan->id">{{ $plan->name }} · {{ $plan->rate_per_student->format() }}</flux:select.option>
            @endforeach
        </flux:select>
        @if ($this->canManage())
            <flux:button type="submit">{{ __('Save plan') }}</flux:button>
        @endif
    </form>
    <flux:error name="planId" />

    @php($lock = $this->lock())
    <div class="flex flex-col gap-3" data-test="lock-state">
        @if ($lock->isLocked())
            <flux:callout variant="danger" icon="lock-closed">
                <flux:callout.heading>{{ __('Read-only since :date', ['date' => $lock->locksOn?->format('j M Y')]) }}</flux:callout.heading>
                <flux:callout.text>{{ __(':number is unpaid. Staff can view and record fee payments but change nothing else; parents are not affected. Paying or voiding it reopens the school.', ['number' => $lock->invoice?->number]) }}</flux:callout.text>
            </flux:callout>
        @elseif ($lock->status === \App\Support\Saas\SaasLock::WARNING)
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.heading>{{ __('Overdue: read-only from :date', ['date' => $lock->locksOn?->format('j M Y')]) }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __(':number is past its due date. Staff see a warning banner.', ['number' => $lock->invoice?->number]) }}
                    @if ($lock->keptOpenUntil)
                        {{ __('Kept open until :date: :reason', ['date' => $lock->keptOpenUntil->format('j M Y'), 'reason' => $school->saas_lock_override_reason]) }}
                    @endif
                </flux:callout.text>
            </flux:callout>
        @else
            <flux:text>{{ __('Nothing overdue: the school is open.') }}</flux:text>
        @endif

        @if ($this->canManage() && $lock->status !== \App\Support\Saas\SaasLock::OPEN)
            <form wire:submit="keepOpen" class="flex flex-wrap items-end gap-3">
                <flux:input type="date" wire:model="keepOpenUntil" :label="__('Keep open until')" class="max-w-48" />
                <flux:input wire:model="keepOpenReason" :label="__('Reason')" :placeholder="__('e.g. half paid, rest agreed by 15 Jan')" class="min-w-72 flex-1" />
                <flux:button type="submit">{{ __('Keep open') }}</flux:button>
                @if ($school->saas_lock_override_until)
                    <flux:button wire:click="stopKeepingOpen" variant="ghost">{{ __('Stop keeping open') }}</flux:button>
                @endif
            </form>
            <flux:error name="until" />
            <flux:error name="keep_reason" />
        @endif
    </div>

    @if ($term = $this->termToBill())
        <flux:callout icon="document-text">
            <flux:callout.heading>{{ __(':term: census date :date has passed', ['term' => $term->name, 'date' => $term->census_on->format('j M Y')]) }}</flux:callout.heading>
            <flux:callout.text>
                {{ __(':count active students on that day. The nightly census issues the invoice; you can issue it now.', ['count' => number_format($this->censusCount($term))]) }}
            </flux:callout.text>
            @if ($this->canManage())
                <x-slot name="actions">
                    <flux:button wire:click="issue" variant="primary" size="sm" data-test="issue">{{ __('Issue invoice now') }}</flux:button>
                </x-slot>
            @endif
        </flux:callout>
    @endif
    <flux:error name="invoice" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Invoice') }}</flux:table.column>
            <flux:table.column>{{ __('Term') }}</flux:table.column>
            <flux:table.column>{{ __('Students × rate') }}</flux:table.column>
            <flux:table.column>{{ __('Amount') }}</flux:table.column>
            <flux:table.column>{{ __('Balance') }}</flux:table.column>
            <flux:table.column>{{ __('Due') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->invoices as $invoice)
                @php($status = $invoice->status())
                <flux:table.row :key="'invoice-'.$invoice->id" data-test="invoice-row">
                    <flux:table.cell class="font-mono text-sm">{{ $invoice->number }}</flux:table.cell>
                    <flux:table.cell>
                        {{ $invoice->term->name }}
                        <flux:text size="sm">{{ __('Census :date', ['date' => $invoice->census_on->format('j M Y')]) }}</flux:text>
                    </flux:table.cell>
                    <flux:table.cell>{{ number_format($invoice->student_count) }} × {{ $invoice->rate_per_student->format() }}</flux:table.cell>
                    <flux:table.cell>{{ $invoice->amount->format() }}</flux:table.cell>
                    <flux:table.cell>{{ $status === \App\Enums\SaasInvoiceStatus::Void ? '—' : $invoice->balance()->format() }}</flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">{{ $invoice->due_on->format('j M Y') }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$status->colour()">{{ $status->label() }}</flux:badge>
                        @if ($invoice->void_reason)
                            <flux:text size="sm">{{ $invoice->void_reason }}</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="text-end whitespace-nowrap">
                        <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('console.invoices.pdf', $invoice)" target="_blank" :aria-label="__('PDF')" />
                        @if ($this->canManage() && in_array($status, [\App\Enums\SaasInvoiceStatus::Unpaid, \App\Enums\SaasInvoiceStatus::Overdue], true))
                            <flux:button size="sm" variant="ghost" wire:click="startPayment({{ $invoice->id }})" data-test="record-payment">{{ __('Record payment') }}</flux:button>
                            @if ($invoice->payments->isEmpty())
                                <flux:button size="sm" variant="ghost" wire:click="startVoid({{ $invoice->id }})">{{ __('Void') }}</flux:button>
                            @endif
                        @endif
                    </flux:table.cell>
                </flux:table.row>
                @foreach ($invoice->payments as $payment)
                    <flux:table.row :key="'payment-'.$payment->id">
                        <flux:table.cell></flux:table.cell>
                        <flux:table.cell colspan="7">
                            <flux:text size="sm">
                                {{ __('Paid :amount on :date by :method', ['amount' => $payment->amount->format(), 'date' => $payment->paid_on->format('j M Y'), 'method' => $payment->method->label()]) }}@if ($payment->reference) · {{ $payment->reference }}@endif · {{ __('recorded by :name', ['name' => $payment->recorder?->name ?? '—']) }}
                            </flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8">{{ __('No invoices yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @if ($this->canManage())
    <flux:modal name="saas-payment" class="md:w-[28rem]">
        <form wire:submit="recordPayment" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Record payment') }}</flux:heading>
            <flux:input wire:model="payment.amount" :label="__('Amount (GH₵)')" inputmode="decimal" />
            <flux:error name="amount" />
            <flux:input wire:model="payment.paid_on" type="date" :label="__('Paid on')" />
            <flux:error name="paid_on" />
            <flux:select wire:model="payment.method" :label="__('Method')">
                @foreach (\App\Enums\SaasPaymentMethod::cases() as $method)
                    <flux:select.option :value="$method->value">{{ $method->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="payment.reference" :label="__('Reference (optional)')" :placeholder="__('e.g. bank or MoMo transaction ID')" />
            <flux:input wire:model="payment.note" :label="__('Note (optional)')" />
            <flux:text size="sm">{{ __('Payments cannot be changed once recorded.') }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Record') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="saas-void" class="md:w-[28rem]">
        <form wire:submit="void" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Void invoice') }}</flux:heading>
            <flux:text>{{ __('The number is not reused. The term can then be invoiced again.') }}</flux:text>
            <flux:input wire:model="voidReason" :label="__('Reason')" :placeholder="__('e.g. census count included withdrawn students')" />
            <flux:error name="reason" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Void') }}</flux:button>
            </div>
        </form>
    </flux:modal>
    @endif
</div>
