@php
    /** @var \App\Support\Results\ReportCardData $d */
    $colour = $d->school->primary_colour ?: '#0A7D3C';
    $student = $d->card->student;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Report card {{ $student->full_name }}, {{ $d->term->name }}</title>
    {{-- dompdf understands basic CSS 2.1 only: tables for layout, no flexbox or Tailwind. --}}
    <style>
        @page { margin: 14mm 13mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #18181b; line-height: 1.35; }
        table { border-collapse: collapse; }
        .header { width: 100%; border-bottom: 3px solid {{ $colour }}; padding-bottom: 6px; }
        .header td { vertical-align: middle; }
        .logo { height: 52px; }
        .school { font-size: 15pt; font-weight: bold; }
        .meta { color: #52525b; font-size: 8pt; }
        .title { text-align: right; }
        .title .main { font-size: 12pt; font-weight: bold; color: {{ $colour }}; }
        .title .sub { font-size: 8.5pt; color: #52525b; }
        table.who { width: 100%; margin: 10px 0 12px; }
        table.who td { padding: 3px 6px 3px 0; vertical-align: top; }
        table.who .label { color: #71717a; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.5px; }
        table.who .value { font-size: 10pt; font-weight: bold; }
        table.results { width: 100%; }
        table.results th { background: #f4f4f5; color: #3f3f46; font-size: 7.5pt; font-weight: bold; text-align: left; padding: 5px 5px; border-bottom: 1.5px solid {{ $colour }}; }
        table.results td { padding: 5px 5px; border-bottom: 1px solid #e4e4e7; vertical-align: top; }
        table.results .num { text-align: center; font-variant-numeric: tabular-nums; }
        table.results .grade { text-align: center; font-weight: bold; }
        table.summary { width: 100%; margin-top: 10px; }
        table.summary td { padding: 6px 8px; background: #f4f4f5; font-size: 9pt; }
        table.summary .big { font-size: 12pt; font-weight: bold; }
        .section { margin-top: 12px; }
        .section h3 { font-size: 8pt; color: #71717a; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 3px; }
        .remark { border: 1px solid #e4e4e7; padding: 6px 8px; min-height: 26px; }
        table.signs { width: 100%; margin-top: 22px; }
        table.signs td { width: 50%; padding-right: 20px; font-size: 8pt; color: #52525b; }
        table.signs .line { border-top: 1px solid #a1a1aa; padding-top: 3px; }
        .key { margin-top: 14px; font-size: 7.5pt; color: #52525b; }
        .key td { padding: 1px 8px 1px 0; }
        .muted { color: #a1a1aa; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            @if ($d->logo)
                <td style="width: 62px"><img src="{{ $d->logo }}" class="logo" alt=""></td>
            @endif
            <td>
                <div class="school">{{ $d->school->name }}</div>
                <div class="meta">{{ collect([$d->school->address, $d->school->phone, $d->school->email])->filter()->implode(', ') }}</div>
            </td>
            <td class="title">
                <div class="main">End of term report</div>
                <div class="sub">{{ $d->term->name }}, {{ $d->term->academicYear->name }}</div>
            </td>
        </tr>
    </table>

    <table class="who">
        <tr>
            <td style="width: 38%"><div class="label">Name</div><div class="value">{{ $student->full_name }}</div></td>
            <td style="width: 18%"><div class="label">Admission no.</div><div class="value">{{ $student->admission_number ?? '–' }}</div></td>
            <td style="width: 16%"><div class="label">Class</div><div class="value">{{ $d->card->stream->full_name }}</div></td>
            <td style="width: 14%"><div class="label">On roll</div><div class="value">{{ $d->card->class_size ?: '–' }}</div></td>
            @if ($d->showPositions && $d->card->position)
                <td style="width: 14%"><div class="label">Position</div><div class="value">{{ \App\Support\Academic\Ranking::ordinal($d->card->position) }}</div></td>
            @endif
        </tr>
        <tr>
            <td><div class="label">Attendance</div><div class="value">{{ $d->attendance['total'] > 0 ? $d->attendance['present'].' of '.$d->attendance['total'].' days' : '–' }}</div></td>
            <td colspan="2"><div class="label">Term</div><div class="value">{{ $d->term->starts_on->format('j M') }} to {{ $d->term->ends_on->format('j M Y') }}</div></td>
            <td colspan="2"><div class="label">Next term begins</div><div class="value">{{ $d->nextTerm?->starts_on->format('j M Y') ?? 'To be announced' }}</div></td>
        </tr>
    </table>

    @yield('results')

    <table style="width: 100%" class="section">
        <tr>
            <td style="width: 50%; padding-right: 8px; vertical-align: top">
                <h3>Conduct</h3>
                <div class="remark">{{ $d->conduct ?? '' }}</div>
            </td>
            <td style="width: 50%; vertical-align: top">
                <h3>Interest</h3>
                <div class="remark">{{ $d->card->interest ?? '' }}</div>
            </td>
        </tr>
    </table>

    <div class="section">
        <h3>Class teacher's remark{{ $d->classTeacher ? ' ('.$d->classTeacher.')' : '' }}</h3>
        <div class="remark">{{ $d->card->teacher_remark ?? '' }}</div>
    </div>

    <div class="section">
        <h3>Head's remark</h3>
        <div class="remark">{{ $d->card->head_remark ?? '' }}</div>
    </div>

    <table class="signs">
        <tr>
            <td><div class="line">Class teacher's signature</div></td>
            <td><div class="line">Head's signature and stamp</div></td>
        </tr>
    </table>

    @yield('key')
</body>
</html>
