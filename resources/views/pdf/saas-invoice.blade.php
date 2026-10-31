<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->number }}</title>
    {{-- dompdf understands basic CSS 2.1 only: no Tailwind, no flexbox. --}}
    <style>
        @page { margin: 18mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #18181b; }
        .header { width: 100%; border-bottom: 3px solid #ea580c; padding-bottom: 8px; margin-bottom: 16px; }
        .header td { vertical-align: top; }
        .from { font-size: 15pt; font-weight: bold; color: #ea580c; }
        .meta { color: #52525b; font-size: 8.5pt; }
        .title { font-size: 18pt; font-weight: bold; letter-spacing: 2px; text-align: right; }
        .parties { width: 100%; margin-bottom: 16px; }
        .parties td { vertical-align: top; width: 50%; }
        .label { color: #71717a; font-size: 8pt; text-transform: uppercase; letter-spacing: 1px; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.lines th { text-align: left; font-size: 8.5pt; color: #52525b; border-bottom: 1px solid #a1a1aa; padding: 5px 4px; }
        table.lines td { padding: 6px 4px; border-bottom: 1px solid #e4e4e7; vertical-align: top; }
        .right { text-align: right; }
        .total td { font-weight: bold; border-bottom: none; }
        .balance td { font-size: 12pt; font-weight: bold; border-top: 2px solid #18181b; }
        .mono { font-family: DejaVu Sans Mono, monospace; }
        .stamp { position: absolute; top: 40%; left: 20%; width: 60%; text-align: center; font-size: 54pt; font-weight: bold;
                 color: #dc2626; border: 5px solid #dc2626; padding: 6px; transform: rotate(-18deg); opacity: 0.55; }
        .pay { margin-top: 20px; padding: 10px; background: #fafafa; border: 1px solid #e4e4e7; }
        .footer { margin-top: 24px; font-size: 8pt; color: #71717a; }
    </style>
</head>
<body>
    @if ($invoice->voided_at)
        <div class="stamp">VOID</div>
    @endif

    <table class="header">
        <tr>
            <td>
                <div class="from">{{ $from['from_name'] }}</div>
                <div class="meta">{{ collect([$from['from_address'], $from['from_phone'], $from['from_email']])->filter()->implode(' · ') }}</div>
            </td>
            <td class="title">INVOICE</td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <div class="label">Billed to</div>
                <div><strong>{{ $invoice->school->name }}</strong></div>
                <div class="meta">{{ collect([$invoice->school->address, $invoice->school->phone, $invoice->school->email])->filter()->implode(' · ') }}</div>
            </td>
            <td class="right">
                <div><span class="label">Invoice</span> <span class="mono">{{ $invoice->number }}</span></div>
                <div><span class="label">Issued</span> {{ $invoice->issued_at->format('j M Y') }}</div>
                <div><span class="label">Due</span> {{ $invoice->due_on->format('j M Y') }}</div>
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Description</th>
                <th class="right">Students</th>
                <th class="right">Rate</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    School management system, {{ $invoice->term->name }} ({{ $invoice->plan_name }} plan)<br>
                    <span class="meta">Active students on the census date, {{ $invoice->census_on->format('j F Y') }}</span>
                </td>
                <td class="right">{{ number_format($invoice->student_count) }}</td>
                <td class="right">{{ $invoice->rate_per_student->format() }}</td>
                <td class="right">{{ $invoice->amount->format() }}</td>
            </tr>
            @foreach ($invoice->payments as $payment)
                <tr>
                    <td colspan="3">Payment received {{ $payment->paid_on->format('j M Y') }} ({{ $payment->method->label() }}@if ($payment->reference), {{ $payment->reference }}@endif)</td>
                    <td class="right">−{{ $payment->amount->format() }}</td>
                </tr>
            @endforeach
            <tr class="balance">
                <td colspan="3">Balance due</td>
                <td class="right">{{ $invoice->voided_at ? '—' : $invoice->balance()->format() }}</td>
            </tr>
        </tbody>
    </table>

    @if ($from['payment_details'] && ! $invoice->voided_at)
        <div class="pay">
            <div class="label">How to pay</div>
            <div>{{ $from['payment_details'] }}</div>
            <div class="meta">Please quote {{ $invoice->number }} as the reference.</div>
        </div>
    @endif

    @if ($invoice->voided_at)
        <p><strong>Voided {{ $invoice->voided_at->format('j M Y') }}:</strong> {{ $invoice->void_reason }}</p>
    @endif

    <div class="footer">
        Orange Tree charges per active student per term, counted on each term's census date.
        Fee payments from parents settle directly to the school; this invoice is only for the use of the system.
    </div>
</body>
</html>
