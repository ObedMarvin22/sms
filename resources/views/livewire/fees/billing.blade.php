<div class="flex max-w-5xl flex-col gap-6" @if ($this->running()) wire:poll.3s @endif>
    <div>
        <flux:heading size="xl">{{ __('Billing') }}</flux:heading>
        <flux:text>{{ __('Issues a bill to every active student for the term, from their class\'s fee structure with their discounts, scholarships and instalment plan. Students already billed are left alone, so you can run it again for late admissions.') }}</flux:text>
    </div>

    @if ($this->terms->isEmpty())
        <flux:text>{{ __('Set up the current academic year first.') }}</flux:text>
    @else
        <flux:select wire:model.live="termId" :label="__('Term')" class="max-w-72" data-test="billing-term">
            @foreach ($this->terms as $term)
                <flux:select.option :value="$term->id">{{ $term->name }} ({{ $term->starts_on->format('j M') }} – {{ $term->ends_on->format('j M Y') }})</flux:select.option>
            @endforeach
        </flux:select>

        @if ($preview = $this->preview())
            <div class="grid gap-3 sm:grid-cols-3">
                <div><flux:text size="sm">{{ __('To bill') }}</flux:text><div class="text-xl" data-test="to-bill">{{ $preview['to_bill'] }}</div></div>
                <div><flux:text size="sm">{{ __('Already billed') }}</flux:text><div class="text-xl">{{ $preview['already_billed'] }}</div></div>
                <div><flux:text size="sm">{{ __('Estimate before discounts') }}</flux:text><div class="text-xl">{{ $preview['estimate']->format() }}</div></div>
            </div>

            @if ($preview['missing_structures'] > 0)
                <flux:callout variant="warning" icon="exclamation-triangle" data-test="missing-structures">
                    <flux:callout.text>
                        {{ trans_choice('{1} :count student has no fee structure for their class and will be skipped. Add it under Fee structures first.|[2,*] :count students have no fee structure for their class and will be skipped. Add them under Fee structures first.', $preview['missing_structures'], ['count' => $preview['missing_structures']]) }}
                    </flux:callout.text>
                </flux:callout>
            @endif

            @if ($preview['groups'] !== [])
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Class') }}</flux:table.column>
                        <flux:table.column>{{ __('Students') }}</flux:table.column>
                        <flux:table.column>{{ __('Estimate') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($preview['groups'] as $group)
                            <flux:table.row :key="$group['class'].'-'.$group['residence']->value">
                                <flux:table.cell>{{ $group['class'] }} · {{ $group['residence']->label() }}</flux:table.cell>
                                <flux:table.cell>{{ $group['students'] }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($group['has_structure'])
                                        {{ $group['estimate']->format() }}
                                    @else
                                        <flux:badge size="sm" color="amber">{{ __('No fee structure') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif

            @if ($this->canManage() && $preview['to_bill'] > 0 && ! $this->running())
                <div>
                    <flux:button variant="primary" icon="document-text" wire:click="start" data-test="start-billing"
                        wire:confirm="{{ __('Issue bills to :count students? Bills cannot be edited afterwards; mistakes are corrected with adjustments.', ['count' => $preview['to_bill']]) }}">
                        {{ trans_choice('{1} Bill :count student|[2,*] Bill :count students', $preview['to_bill'], ['count' => $preview['to_bill']]) }}
                    </flux:button>
                </div>
            @endif
        @endif

        @foreach ($this->runs() as $run)
            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" wire:key="run-{{ $run->id }}" data-test="billing-run">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <flux:text>
                        {{ __('Started :when by :name', ['when' => $run->created_at->format('j M Y H:i'), 'name' => $run->user->name ?? __('a former user')]) }}
                    </flux:text>
                    @if ($run->status === \App\Enums\BillingRunStatus::Completed)
                        <flux:badge color="green">{{ __('Finished: :total billed', ['total' => $run->total_net->format()]) }}</flux:badge>
                    @else
                        <flux:badge color="amber" icon="arrow-path">{{ __('In progress') }}</flux:badge>
                    @endif
                </div>
                <flux:text class="mt-1">
                    {{ __(':billed of :total billed, :skipped skipped', ['billed' => $run->billedCount(), 'total' => $run->total_students, 'skipped' => $run->skippedCount()]) }}
                </flux:text>
                @if ($run->skippedCount() > 0)
                    <ul class="mt-2 list-disc ps-5 text-sm">
                        @foreach ($run->skipped ?? [] as $skip)
                            <li>{{ $skip['name'] }}: {{ $skip['reason'] }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    @endif
</div>
