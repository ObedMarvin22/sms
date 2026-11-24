@extends('pdf.report-cards.layout')

@php
    $fmt = fn ($v) => $v === null ? '–' : rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
    $first = $d->results->first();
    $split = $first ? \App\Support\Results\ReportCardData::split($first) : null;
@endphp

@section('results')
    <table class="results">
        <thead>
            <tr>
                <th style="width: 34%">Subject</th>
                <th class="num">Class score{{ $split ? ' ('.$split['class'][1].')' : '' }}</th>
                <th class="num">Exam{{ $split ? ' ('.$split['exam'][1].')' : '' }}</th>
                <th class="num">Total (100)</th>
                <th class="num">Grade</th>
                <th style="width: 22%">Meaning</th>
                @if ($d->showPositions)
                    <th class="num">Position</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse ($d->results as $result)
                @php($parts = \App\Support\Results\ReportCardData::split($result))
                <tr>
                    <td>{{ $result->subject->name }}</td>
                    <td class="num">{{ $fmt($parts['class'][0]) }}</td>
                    <td class="num">{{ $fmt($parts['exam'][0]) }}</td>
                    <td class="num"><strong>{{ $fmt($result->total) }}</strong></td>
                    <td class="grade">{{ $result->grade ?? '–' }}</td>
                    <td>{{ $result->grade_label ?? '' }}</td>
                    @if ($d->showPositions)
                        <td class="num">{{ $result->subject_position ? \App\Support\Academic\Ranking::ordinal($result->subject_position) : '–' }}</td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No subject results for this term yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="summary">
        <tr>
            <td>Subjects: <span class="big">{{ $d->card->subjects_count }}</span></td>
            <td>Average: <span class="big">{{ $fmt($d->card->average) }}</span></td>
            @yield('summary-extra')
        </tr>
    </table>
@endsection

@section('key')
    @if ($d->scale)
        <table class="key">
            <tr>
                <td><strong>Grades:</strong></td>
                @foreach ($d->scale->bands as $i => $band)
                    <td>{{ $band->grade }} {{ $band->label }} ({{ $band->min_score }}{{ $i === 0 ? '–100' : '–'.($d->scale->bands[$i - 1]->min_score - 1) }})</td>
                @endforeach
            </tr>
        </table>
    @endif
@endsection
