<div class="flex max-w-4xl flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Attendance') }}</flux:heading>
        <flux:text>{{ __('Take the register every school day. It locks at :time; after that only an administrator can change it.', ['time' => $this->lockTime()]) }}</flux:text>
    </div>

    @if ($this->streams()->isEmpty())
        <flux:callout variant="warning" icon="user-group">
            <flux:callout.heading>{{ __('You are not a class teacher this year') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Ask an administrator to make you the class teacher of a stream under Teaching assignments.') }}</flux:callout.text>
        </flux:callout>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:select wire:model.live="streamId" :label="__('Stream')" data-test="register-stream">
                @foreach ($this->streams() as $stream)
                    <flux:select.option :value="$stream->id">{{ $stream->full_name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input type="date" wire:model.live="date" :label="__('Date')" :max="now()->toDateString()" />
        </div>

        @if ($problem = $this->dayProblem())
            <flux:callout variant="warning" icon="calendar-days"><flux:callout.text>{{ $problem }}</flux:callout.text></flux:callout>
        @else
            @php($session = $this->session())
            @php($locked = $this->isLocked())

            @if ($locked)
                <flux:callout :variant="$this->canEditLocked() ? 'secondary' : 'warning'" icon="lock-closed" data-test="locked">
                    <flux:callout.heading>{{ __('This register locked at :time', ['time' => $this->lockTime()]) }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ $this->canEditLocked() ? __('You can still change it. Give a reason; each change is logged.') : __('Ask an administrator if a mark needs changing.') }}
                    </flux:callout.text>
                </flux:callout>
            @endif

            <flux:text data-test="register-status">
                @if ($session)
                    {{ __('Taken by :name at :time.', ['name' => $session->takenBy->name ?? __('a former user'), 'time' => $session->taken_at?->format('H:i')]) }}
                @else
                    {{ __('Not taken yet. Everyone starts as present; change the ones who are not.') }}
                @endif
            </flux:text>

            @if ($this->enrolments()->isEmpty())
                <flux:text>{{ __('No students are in this stream on this day.') }}</flux:text>
            @else
                <form wire:submit="save" class="flex flex-col gap-3">
                    @php($readOnly = $locked && ! $this->canEditLocked())
                    @unless ($readOnly)
                        <div class="flex gap-2">
                            <flux:button size="sm" wire:click="markAll('present')">{{ __('Mark all present') }}</flux:button>
                        </div>
                    @endunless

                    <flux:error name="marks" />
                    @foreach ($this->enrolments() as $enrolment)
                        <div class="grid items-center gap-2 border-b border-zinc-100 py-2 sm:grid-cols-[1fr_auto_12rem] dark:border-zinc-700" wire:key="mark-{{ $enrolment->student_id }}" data-test="register-row">
                            <div>
                                {{ $enrolment->student->full_name }}
                                <flux:text size="sm" class="inline font-mono">{{ $enrolment->student->admission_number }}</flux:text>
                            </div>
                            <flux:radio.group wire:model="marks.{{ $enrolment->student_id }}.status" variant="segmented" size="sm" :disabled="$readOnly">
                                @foreach ($this->statuses() as $status)
                                    <flux:radio :value="$status->value" :label="$status->label()" />
                                @endforeach
                            </flux:radio.group>
                            <flux:input size="sm" wire:model="marks.{{ $enrolment->student_id }}.note" :placeholder="__('Note')" :disabled="$readOnly" />
                            <flux:error name="marks.{{ $enrolment->student_id }}" />
                        </div>
                    @endforeach

                    @unless ($readOnly)
                        @if ($locked)
                            <flux:input wire:model="reason" :label="__('Reason for the change')" data-test="reason" />
                            <flux:error name="reason" />
                        @endif
                        <flux:error name="date" />
                        <div>
                            <flux:button type="submit" variant="primary" data-test="save-register">
                                {{ __('Save register (:count students)', ['count' => $this->enrolments()->count()]) }}
                            </flux:button>
                        </div>
                    @endunless
                </form>
            @endif
        @endif
    @endif
</div>
