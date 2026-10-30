<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $payment->receipt_number }}</title>
    {{-- dompdf understands basic CSS 2.1 only: no Tailwind, no flexbox. --}}
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #18181b; }
        .header { width: 100%; border-bottom: 2px solid {{ $school->primary_colour ?? '#ea580c' }}; padding-bottom: 6px; margin-bottom: 12px; }
        .header td { vertical-align: middle; }
        .logo { height: 44px; }
        .school { font-size: 14pt; font-weight: bold; }
        .meta { color: #52525b; font-size: 8.5pt; }
        .title { font-size: 13pt; font-weight: bold; letter-spacing: 1px; }
        table.details { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.details td { padding: 4px 0; border-bottom: 1px solid #e4e4e7; vertical-align: top; }
        table.details td.label { color: #52525b; width: 38%; }
        .amount { font-size: 16pt; font-weight: bold; }
        .words { font-style: italic; }
        .mono { font-family: DejaVu Sans Mono, monospace; }
        .stamp { position: absolute; top: 45%; left: 8%; width: 84%; text-align: center; font-size: 44pt; font-weight: bold;
                  color: #dc2626; border: 4px solid #dc2626; padding: 6px; transform: rotate(-18deg); opacity: 0.6; }
        .footer { margin-top: 18px; font-size: 8pt; color: #71717a; }
    </style>
</head>
<body>
    @if ($payment->isReversed())
        <div class="stamp">REVERSED</div>
    @endif

    <table class="header">
        <tr>
            @if ($logo)
                <td style="width: 54px"><img src="{{ $logo }}" class="logo" alt=""></td>
            @endif
            <td>
                <div class="school">{{ $school->name }}</div>
                <div class="meta">{{ collect([$school->address, $school->phone, $school->email])->filter()->implode(' · ') }}</div>
            </td>
            <td style="text-align: right">
                <div class="title">RECEIPT</div>
                <div class="mono">{{ $payment->receipt_number }}</div>
            </td>
        </tr>
    </table>

    <table class="details">
        <tr><td class="label">Received from (for)</td><td>{{ $payment->student->full_name }} <span class="mono">({{ $payment->student->admission_number }})</span></td></tr>
        <tr><td class="label">Class</td><td>{{ $payment->student->currentEnrolment?->stream->full_name ?? '—' }}</td></tr>
        <tr><td class="label">Amount</td><td><span class="amount">{{ $payment->amount->format() }}</span></td></tr>
        <tr><td class="label">In words</td><td class="words">{{ $words }}</td></tr>
        <tr><td class="label">Paid by</td><td>{{ $payment->method->label() }}@if ($payment->reference) · <span class="mono">{{ $payment->reference }}</span>@endif</td></tr>
        <tr><td class="label">Date paid</td><td>{{ $payment->paid_on->format('j M Y') }}</td></tr>
        <tr>
            <td class="label">Balance after this payment</td>
            <td>{{ $balanceAfter->pesewas < 0 ? 'In credit: '.\App\Support\Money\Money::pesewas(-$balanceAfter->pesewas)->format() : $balanceAfter->format() }}</td>
        </tr>
        @if ($payment->note)
            <tr><td class="label">Note</td><td>{{ $payment->note }}</td></tr>
        @endif
        <tr><td class="label">Received by</td><td>{{ $payment->recorder->name ?? '—' }} on {{ $payment->created_at->format('j M Y H:i') }}</td></tr>
        @if ($payment->isReversed())
            <tr><td class="label">Reversed</td><td>{{ $payment->reversed_at?->format('j M Y') }}: {{ $payment->reversal_reason }}</td></tr>
        @endif
    </table>

    <div class="footer">Keep this receipt. Fees are not refundable except as the school's policy allows.</div>
</body>
</html>
