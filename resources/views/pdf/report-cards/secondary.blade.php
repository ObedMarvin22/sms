{{-- JHS and SHS: the primary layout, plus grade points and the aggregate. --}}
@extends('pdf.report-cards.primary')

@section('summary-extra')
    <td>Aggregate: <span class="big">{{ $d->card->aggregate ?? '–' }}</span>
        <span style="font-size: 7.5pt; color: #71717a">
            ({{ $d->level === \App\Enums\SchoolLevel::Jhs ? 'English, Mathematics, Science, Social Studies + best 2' : 'English, Core Mathematics, Integrated Science + best 3' }}; lower is better)
        </span>
    </td>
@endsection
