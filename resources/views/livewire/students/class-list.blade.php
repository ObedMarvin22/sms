<div class="flex max-w-5xl flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Class lists') }}</flux:heading>
        <flux:text>{{ __('Students in each stream today, with their primary contact.') }}</flux:text>
    </div>

    @if ($this->streams->isEmpty())
        <flux:text>{{ __('No classes yet.') }}</flux:text>
    @else
        <div class="flex flex-wrap items-end justify-between gap-4">
            <flux:select wire:model.live="streamId" :label="__('Stream')" class="max-w-60" data-test="class-list-stream">
                @foreach ($this->streams as $stream)
                    <flux:select.option :value="$stream->id">{{ $stream->full_name }}</flux:select.option>
                @endforeach
            </flux:select>
            @if ($selected = $this->selectedStream())
                <flux:button :href="route('class-lists.pdf', $selected)" icon="arrow-down-tray" data-test="class-list-pdf">{{ __('Download PDF') }}</flux:button>
            @endif
        </div>

        @if ($list = $this->classList())
            <flux:text data-test="class-list-totals">
                {{ __(':total students: :boys boys, :girls girls', ['total' => $list['total'], 'boys' => $list['boys'], 'girls' => $list['girls']]) }}
            </flux:text>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>#</flux:table.column>
                    <flux:table.column>{{ __('Admission no.') }}</flux:table.column>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Gender') }}</flux:table.column>
                    <flux:table.column>{{ __('Date of birth') }}</flux:table.column>
                    <flux:table.column>{{ __('Primary contact') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($list['rows'] as $i => $row)
                        <flux:table.row :key="'row-'.$row['admission_number']" data-test="class-list-row">
                            <flux:table.cell>{{ $i + 1 }}</flux:table.cell>
                            <flux:table.cell class="font-mono text-sm">{{ $row['admission_number'] }}</flux:table.cell>
                            <flux:table.cell>{{ $row['name'] }}</flux:table.cell>
                            <flux:table.cell>{{ $row['gender']?->label() }}</flux:table.cell>
                            <flux:table.cell>{{ $row['date_of_birth']?->format('j M Y') }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($row['guardian'])
                                    {{ $row['guardian'] }} <span class="font-mono text-sm text-zinc-500">{{ $row['guardian_phone'] }}</span>
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6">{{ __('No students are in this stream today.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        @endif
    @endif
</div>
