<div class="flex max-w-6xl flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Payments') }}</flux:heading>
            <flux:text>{{ __('Every payment gets a numbered receipt. A mistake is reversed, never edited, and the receipt number is not reused.') }}</flux:text>
        </div>
        @if ($this->canRecord())
            <flux:button variant="primary" icon="banknotes" wire:click="startRecord" data-test="record-payment">{{ __('Record payment') }}</flux:button>
        @endif
    </div>

    <div class="grid gap-3 sm:grid-cols-[1fr_10rem_10rem_10rem]">
        <flux:input wire:model.live.debounce.300ms="search" :label="__('Search')" icon="magnifying-glass" :placeholder="__('Receipt, reference, student or admission number')" />
        <flux:select wire:model.live="methodFilter" :label="__('Method')">
            <flux:select.option value="">{{ __('All') }}</flux:select.option>
            @foreach ($this->methods() as $method)
                <flux:select.option :value="$method->value">{{ $method->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:input type="date" wire:model.live="from" :label="__('From')" />
        <flux:input type="date" wire:model.live="to" :label="__('To')" />
    </div>

    <flux:table :paginate="$this->payments">
        <flux:table.columns>
            <flux:table.column>{{ __('Receipt') }}</flux:table.column>
            <flux:table.column>{{ __('Student') }}</flux:table.column>
            <flux:table.column>{{ __('Amount') }}</flux:table.column>
            <flux:table.column>{{ __('Method') }}</flux:table.column>
            <flux:table.column>{{ __('Paid') }}</flux:table.column>
            <flux:table.column>{{ __('Recorded by') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->payments as $payment)
                <flux:table.row :key="'payment-'.$payment->id" data-test="payment-row">
                    <flux:table.cell class="font-mono text-sm">
                        {{ $payment->receipt_number }}
                        @if ($payment->isReversed())
                            <flux:badge size="sm" color="red" :title="$payment->reversal_reason">{{ __('Reversed') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:link :href="route('students.show', $payment->student)" wire:navigate>{{ $payment->student->full_name }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell class="font-mono">{{ $payment->amount->format() }}</flux:table.cell>
                    <flux:table.cell>
                        {{ $payment->method->label() }}
                        @if ($payment->reference)
                            <flux:text size="sm" class="font-mono">{{ $payment->reference }}</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $payment->paid_on->format('j M Y') }}</flux:table.cell>
                    <flux:table.cell>{{ $payment->recorder->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell class="text-end whitespace-nowrap">
                        <flux:button size="sm" variant="ghost" icon="document-arrow-down" :href="route('receipts.pdf', $payment)" :aria-label="__('Receipt')" data-test="receipt-link" />
                        @if ($payment->proof_path)
                            <flux:button size="sm" variant="ghost" icon="paper-clip" :href="route('payments.proof', $payment)" target="_blank" :aria-label="__('Proof')" />
                        @endif
                        @if ($this->canReverse() && ! $payment->isReversed())
                            <flux:button size="sm" variant="ghost" icon="arrow-uturn-left" wire:click="startReverse({{ $payment->id }})" :aria-label="__('Reverse')" data-test="reverse" />
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7">{{ __('No payments found.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @if ($this->canRecord())
        <flux:modal name="record-payment" class="md:w-[32rem]">
            <form wire:submit="record" class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('Record payment') }}</flux:heading>

                @if ($student = $this->selectedStudent())
                    @php($balance = $this->selectedBalance())
                    <div class="flex items-start justify-between gap-2 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800" data-test="selected-student">
                        <div>
                            <div>{{ $student->full_name }} <span class="font-mono text-sm text-zinc-500">{{ $student->admission_number }}</span></div>
                            <flux:text size="sm">
                                {{ $student->currentEnrolment?->stream->full_name }} ·
                                {{ $balance->pesewas < 0 ? __('In credit: :amount', ['amount' => \App\Support\Money\Money::pesewas(-$balance->pesewas)->format()]) : __('Owes: :amount', ['amount' => $balance->format()]) }}
                            </flux:text>
                        </div>
                        <flux:button size="sm" variant="ghost" wire:click="$set('studentId', '')">{{ __('Change') }}</flux:button>
                    </div>
                @else
                    <flux:input wire:model.live.debounce.300ms="studentSearch" :label="__('Student')" icon="magnifying-glass" :placeholder="__('Name or admission number')" data-test="student-search" />
                    @foreach ($this->studentMatches() as $match)
                        <button type="button" class="text-start text-sm hover:underline" wire:click="pickStudent({{ $match->id }})" wire:key="match-{{ $match->id }}" data-test="student-match">
                            {{ $match->full_name }} · {{ $match->admission_number }} · {{ $match->currentEnrolment?->stream->full_name }}
                        </button>
                    @endforeach
                @endif
                <flux:error name="student" />

                <flux:input wire:model.live.debounce.400ms="form.amount" :label="__('Amount (GH₵)')" inputmode="decimal" class="max-w-48" data-test="amount" />
                <flux:error name="amount" />
                @if ($warning = $this->overpayment())
                    <flux:callout variant="warning" icon="information-circle"><flux:callout.text>{{ $warning }}</flux:callout.text></flux:callout>
                @endif

                <flux:radio.group wire:model.live="form.method" variant="segmented" :label="__('Paid by')" size="sm">
                    @foreach ($this->methods() as $method)
                        <flux:radio :value="$method->value" :label="$method->label()" />
                    @endforeach
                </flux:radio.group>
                <flux:error name="method" />

                @if ($this->selectedMethod()->needsReference())
                    <flux:input wire:model="form.reference" :label="__($this->selectedMethod()->referenceLabel())" data-test="reference" />
                    <flux:error name="reference" />
                @endif

                <flux:input type="date" wire:model="form.paid_on" :label="__('Date paid')" :max="now()->toDateString()" class="max-w-48" />
                <flux:error name="paid_on" />

                <flux:input type="file" wire:model="proof" :label="$this->selectedMethod()->needsProof() ? __('Proof (photo or PDF of the slip)') : __('Proof (optional)')" accept=".jpg,.jpeg,.png,.pdf" />
                <flux:error name="proof" />

                <flux:input wire:model="form.note" :label="__('Note (optional)')" />
                <flux:error name="note" />

                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Record and issue receipt') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif

    @if ($this->canReverse())
        <flux:modal name="reverse-payment" class="md:w-[28rem]">
            <form wire:submit="reverse" class="flex flex-col gap-4">
                @if ($payment = $this->reversing())
                    <flux:heading size="lg">{{ __('Reverse receipt :number', ['number' => $payment->receipt_number]) }}</flux:heading>
                    <flux:text>
                        {{ __(':amount for :name goes back onto their balance. The receipt is kept, marked reversed.', ['amount' => $payment->amount->format(), 'name' => $payment->student->full_name]) }}
                    </flux:text>
                @endif
                <flux:input wire:model="reason" :label="__('Reason')" :placeholder="__('e.g. Deposit bounced, wrong student')" />
                <flux:error name="reason" />
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="danger">{{ __('Reverse payment') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>
