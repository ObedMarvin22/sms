<div class="flex max-w-4xl flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $student->full_name }}</flux:heading>
            <flux:text class="font-mono">{{ $student->admission_number }}</flux:text>
            <div class="mt-2 flex gap-2">
                <flux:badge size="sm">{{ $student->status->label() }}</flux:badge>
                @if ($student->currentEnrolment)
                    <flux:badge size="sm" color="green" data-test="current-class">{{ $student->currentEnrolment->stream->full_name }}</flux:badge>
                @endif
            </div>
        </div>
        <div class="flex gap-2">
            <flux:button :href="route('students.index')" variant="ghost" icon="arrow-left" wire:navigate>{{ __('Students') }}</flux:button>
            @if ($this->canManage() && $student->currentEnrolment)
                <flux:modal.trigger name="move"><flux:button>{{ __('Move to another stream') }}</flux:button></flux:modal.trigger>
                <flux:modal.trigger name="withdraw"><flux:button variant="danger">{{ __('Leaving') }}</flux:button></flux:modal.trigger>
            @endif
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div><flux:text size="sm">{{ __('Gender') }}</flux:text><div>{{ $student->gender?->label() ?? '—' }}</div></div>
        <div><flux:text size="sm">{{ __('Date of birth') }}</flux:text><div>{{ $student->date_of_birth?->format('j M Y') ?? '—' }}</div></div>
        <div><flux:text size="sm">{{ __('Admitted') }}</flux:text><div>{{ $student->admitted_on->format('j M Y') }}</div></div>
    </div>

    <livewire:students.guardians :student-id="$student->id" />

    <div>
        <flux:heading size="lg" class="mb-2">{{ __('Enrolment history') }}</flux:heading>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Year') }}</flux:table.column>
                <flux:table.column>{{ __('Class') }}</flux:table.column>
                <flux:table.column>{{ __('From') }}</flux:table.column>
                <flux:table.column>{{ __('To') }}</flux:table.column>
                <flux:table.column>{{ __('Outcome') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($student->enrolments as $enrolment)
                    <flux:table.row :key="'enrolment-'.$enrolment->id" data-test="history-row">
                        <flux:table.cell>{{ $enrolment->academicYear->name }}</flux:table.cell>
                        <flux:table.cell>{{ $enrolment->stream->full_name }}{{ $enrolment->programme ? ' · '.$enrolment->programme->name : '' }}</flux:table.cell>
                        <flux:table.cell>{{ $enrolment->starts_on->format('j M Y') }}</flux:table.cell>
                        <flux:table.cell>{{ $enrolment->ends_on?->format('j M Y') ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $enrolment->status->label() }}
                            @if ($enrolment->reason)<flux:text size="sm">{{ $enrolment->reason }}</flux:text>@endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>

    @if ($this->canManage())
        <flux:modal name="move" class="md:w-96">
            <form wire:submit="move" class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('Move to another stream') }}</flux:heading>
                <flux:select wire:model="moveStreamId" :label="__('New class')">
                    <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                    @foreach ($this->moveOptions() as $id => $label)
                        <flux:select.option :value="$id">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input type="date" wire:model="moveOn" :label="__('From')" />
                <flux:input wire:model="moveReason" :label="__('Reason')" />
                <flux:error name="stream_id" /><flux:error name="programme_id" /><flux:error name="date" />
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Move') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="withdraw" class="md:w-96">
            <form wire:submit="withdraw" class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('Leaving the school') }}</flux:heading>
                <flux:select wire:model="leaving" :label="__('Why')">
                    @foreach ($this->leavingOptions() as $option)
                        <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input type="date" wire:model="leaveOn" :label="__('Last day')" />
                <flux:input wire:model="leaveReason" :label="__('Note')" />
                <flux:error name="status" /><flux:error name="date" />
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="danger">{{ __('Confirm') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>
