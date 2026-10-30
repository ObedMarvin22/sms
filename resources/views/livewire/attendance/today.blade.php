<div class="flex max-w-4xl flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __("Today's attendance") }}</flux:heading>
        <flux:text>{{ now()->format('l j F Y') }}</flux:text>
    </div>

    @if ($problem = $this->dayProblem())
        <flux:callout variant="secondary" icon="calendar-days"><flux:callout.text>{{ $problem }}</flux:callout.text></flux:callout>
    @else
        @php($rows = $this->rows())
        <flux:text data-test="taken-count">
            {{ __(':taken of :total registers taken', ['taken' => collect($rows)->whereNotNull('session')->count(), 'total' => count($rows)]) }}
        </flux:text>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Stream') }}</flux:table.column>
                <flux:table.column>{{ __('Register') }}</flux:table.column>
                <flux:table.column>{{ __('In school') }}</flux:table.column>
                <flux:table.column>{{ __('Absent') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($rows as $row)
                    <flux:table.row :key="'today-'.$row['stream']->id" data-test="today-row">
                        <flux:table.cell>{{ $row['stream']->full_name }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($row['session'])
                                <flux:badge size="sm" color="green">{{ __('Taken :time', ['time' => $row['session']->taken_at?->format('H:i')]) }}</flux:badge>
                                <flux:text size="sm" class="inline">{{ $row['session']->takenBy?->name }}</flux:text>
                            @else
                                <flux:badge size="sm" color="amber">{{ __('Not taken') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $row['session'] ? $row['present'] : '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $row['session'] ? $row['absent'] : '—' }}</flux:table.cell>
                        <flux:table.cell class="text-end">
                            <flux:link :href="route('attendance.register', ['stream' => $row['stream']->id, 'date' => now()->toDateString()])" wire:navigate>{{ __('Open') }}</flux:link>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
