<div class="flex max-w-4xl flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Promotion') }}</flux:heading>
        <flux:text>{{ __('At the end of the year, promote, repeat or graduate the students of each stream. Their history is kept.') }}</flux:text>
    </div>

    @if ($this->currentYear === null)
        <flux:callout variant="warning"><flux:callout.text>{{ __('Set a current academic year first.') }}</flux:callout.text></flux:callout>
    @elseif ($this->nextYears()->isEmpty())
        <flux:callout variant="warning" icon="calendar-days">
            <flux:callout.heading>{{ __('Create next year first') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Students are promoted into the next academic year. Create it under Academic years.') }}</flux:callout.text>
        </flux:callout>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:select wire:model.live="fromStreamId" :label="__('Stream in :year', ['year' => $this->currentYear->name])" data-test="from-stream">
                <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                @foreach ($this->streamOptions as $id => $label)
                    <flux:select.option :value="$id">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model="nextYearId" :label="__('Into year')">
                @foreach ($this->nextYears() as $year)
                    <flux:select.option :value="$year->id">{{ $year->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:error name="students" /><flux:error name="year" /><flux:error name="programme_id" />

        @if ($fromStreamId !== '')
            @if ($this->enrolments->isEmpty())
                <flux:text>{{ __('No students are currently in this stream.') }}</flux:text>
            @else
                <form wire:submit="promote" class="flex flex-col gap-3">
                    @foreach ($this->enrolments as $enrolment)
                        <div class="grid items-center gap-3 sm:grid-cols-[1fr_10rem_10rem]" wire:key="decision-{{ $enrolment->student_id }}">
                            <div>{{ $enrolment->student->full_name }} <flux:text size="sm" class="inline font-mono">{{ $enrolment->student->admission_number }}</flux:text></div>
                            <flux:select wire:model.live="decisions.{{ $enrolment->student_id }}.action">
                                <flux:select.option value="promote">{{ __('Promote') }}</flux:select.option>
                                <flux:select.option value="repeat">{{ __('Repeat') }}</flux:select.option>
                                <flux:select.option value="graduate">{{ __('Graduate') }}</flux:select.option>
                            </flux:select>
                            @if (($decisions[$enrolment->student_id]['action'] ?? '') !== 'graduate')
                                <flux:select wire:model="decisions.{{ $enrolment->student_id }}.stream_id">
                                    <flux:select.option value="">{{ __('Next year\'s stream…') }}</flux:select.option>
                                    @foreach ($this->streamOptions as $id => $label)
                                        <flux:select.option :value="$id">{{ $label }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            @else
                                <span></span>
                            @endif
                        </div>
                    @endforeach

                    <div>
                        <flux:button type="submit" variant="primary" data-test="promote-button">{{ __('Apply to :count students', ['count' => $this->enrolments->count()]) }}</flux:button>
                    </div>
                </form>
            @endif
        @endif
    @endif
</div>
