<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $stream->full_name }} class list</title>
    {{-- dompdf understands basic CSS 2.1 only: no Tailwind, no flexbox. --}}
    <style>
        @page { margin: 18mm 14mm 18mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #18181b; }
        .header { width: 100%; border-bottom: 2px solid {{ $school->primary_colour ?? '#ea580c' }}; padding-bottom: 6px; margin-bottom: 10px; }
        .header td { vertical-align: middle; }
        .logo { height: 48px; }
        .school { font-size: 15pt; font-weight: bold; }
        .meta { color: #52525b; font-size: 9pt; }
        table.list { width: 100%; border-collapse: collapse; }
        table.list th { text-align: left; font-size: 9pt; border-bottom: 1px solid #a1a1aa; padding: 4px; }
        table.list td { border-bottom: 1px solid #e4e4e7; padding: 4px; }
        .mono { font-family: DejaVu Sans Mono, monospace; font-size: 9pt; }
        .footer { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 8pt; color: #71717a; }
        .pagenum:before { content: counter(page); }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            @if ($logo)
                <td style="width: 60px"><img src="{{ $logo }}" class="logo" alt=""></td>
            @endif
            <td>
                <div class="school">{{ $school->name }}</div>
                <div class="meta">
                    {{ $stream->full_name }} class list
                    @if ($year) · {{ $year->name }} @endif
                    @if ($term) · {{ $term->name }} @endif
                </div>
            </td>
            <td style="text-align: right" class="meta">
                {{ $list['total'] }} students ({{ $list['boys'] }} boys, {{ $list['girls'] }} girls)<br>
                Printed {{ $printedOn->format('j M Y') }}
            </td>
        </tr>
    </table>

    <table class="list">
        <thead>
            <tr>
                <th style="width: 22px">#</th>
                <th>Admission no.</th>
                <th>Name</th>
                <th>Gender</th>
                <th>Date of birth</th>
                <th>Primary contact</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($list['rows'] as $i => $row)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td class="mono">{{ $row['admission_number'] }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['gender']?->label() }}</td>
                    <td>{{ $row['date_of_birth']?->format('j M Y') }}</td>
                    <td>{{ $row['guardian'] ?? '—' }} @if ($row['guardian_phone'])<span class="mono">{{ $row['guardian_phone'] }}</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="6">No students are in this stream today.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">{{ $school->name }} · {{ $stream->full_name }} · Page <span class="pagenum"></span></div>
</body>
</html>
