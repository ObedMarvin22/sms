@extends('pdf.report-cards.layout')

@section('results')
    <table class="results">
        <thead>
            <tr>
                <th style="width: 32%">Learning area</th>
                <th>What {{ $d->card->student->first_name }} can do and is working on</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($d->comments as $comment)
                <tr>
                    <td><strong>{{ $comment->subject->name }}</strong></td>
                    <td>{{ $comment->comment }}</td>
                </tr>
            @empty
                <tr><td colspan="2" class="muted">No comments for this term yet.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
