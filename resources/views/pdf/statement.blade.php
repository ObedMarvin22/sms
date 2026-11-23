<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Fee statement {{ $student->admission_number }}</title>
    {{-- dompdf understands basic CSS 2.1 only: no Tailwind, no flexbox. --}}
    <style>
        @page { margin: 16mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #18181b; }
        .header { width: 100%; border-bottom: 2px solid {{ $school->primary_colour ?? '#ea580c' }}; padding-bottom: 6px; margin-bottom: 12px; }
        .header td { vertical-align: middle; }
        .logo { height: 44px; }
        .school { font-size: 14pt; font-weight: bold; }
        .meta { color: #52525b; font-size: 8.5pt; }
        .title { font-size: 13pt; font-weight: bold; letter-spacing: 1px; text-align: right; }
        table.who { width: 100%; margin-bottom: 12px; }
        table.who td { padding: 2px 0; }
        table.who td.label { color: #52525b; width: 22%; }
        table.lines { width: 100%; border-collapse: collapse; }
        table.lines th { text-align: left; font-size: 8pt; color: #52525b; border-bottom: 1px solid #a1a1aa; padding: 5px 4px; }
        table.lines td { padding: 5px 4px; border-bottom: 1px solid #e4e4e7; vertical-align: top; }
        .right { text-align: right; }
        .mono { font-family: DejaVu Sans Mono, monospace; font-size: 8.5pt; }
        .credit { color: #15803d; }
        .total td { font-size: 11pt; font-weight: bold; border-top: 2px solid #18181b; border-bottom: none; }
        .footer { margin-top: 18px; font-size: 8pt; color: #71717a; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            @if ($logo)
                <td style="width: 54px"><img src="{{ $logo }}" class="logo" alt=""></td>
            @endif
            <td>
                <div class="school">{{ $school->name }}</div>
                <div class="meta">{{ collect([$school->address, $school->phone, $school->email])->filter()->implode(' · ') }}</div>
            </td>
            <td class="title">FEE STATEMENT</td>
        </tr>
    </table>

    <table class="who">
        <tr><td class="label">Student</td><td>{{ $student->full_name }} <span class="mono">({{ $student->admission_number }})</span></td></tr>
        <tr><td class="label">Class</td><td>{{ $student->currentEnrolment?->stream->full_name ?? '—' }}</td></tr>
        <tr><td class="label">Printed</td><td>{{ $printedAt->format('j M Y H:i') }}</td></tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th style="width: 14%">Date</th>
                <th>Description</th>
                <th style="width: 18%">Receipt</th>
                <th class="right" style="width: 14%">Charged</th>
                <th class="right" style="width: 14%">Paid</th>
                <th class="right" style="width: 14%">Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @php($amount = $row['entry']->amount)
                <tr>
                    <td>{{ $row['entry']->entry_date->format('j M Y') }}</td>
                    <td>{{ $row['entry']->description }}</td>
                    <td class="mono">{{ $row['entry']->payment?->receipt_number }}</td>
                    <td class="right">{{ $amount->pesewas > 0 ? $amount->format() : '' }}</td>
                    <td class="right credit">{{ $amount->pesewas < 0 ? \App\Support\Money\Money::pesewas(-$amount->pesewas)->format() : '' }}</td>
                    <td class="right">{{ $row['balance']->format() }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Nothing has been billed or paid yet.</td></tr>
            @endforelse
            <tr class="total">
                <td colspan="5">{{ $balance->pesewas < 0 ? 'In credit' : 'Balance to pay' }}</td>
                <td class="right">{{ $balance->pesewas < 0 ? \App\Support\Money\Money::pesewas(-$balance->pesewas)->format() : $balance->format() }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">This statement shows every bill, payment and adjustment on the student's account. Contact the school's accounts office about anything that looks wrong.</div>
</body>
</html>
