@php
    $settings = json_decode($business->hms_settings ?: '{}');
    $pdfSettings = data_get($settings, 'booking_pdf', (object) []);
    $currencyCode = data_get(session('business'), 'currency_code')
        ?: data_get(session('currency'), 'code')
        ?: optional($business->currency)->code
        ?: '';
    $isQuotation = $documentContext['kind'] === 'quotation';
    $discountValue = 0.0;
    if (!empty($transaction->hms_coupon_id) || $transaction->discount_type === 'fixed') {
        $discountValue = (float) $transaction->discount_amount;
    } elseif ($transaction->discount_type === 'percentage') {
        $discountValue = ((float) $transaction->discount_amount / 100) * (float) $transaction->total_before_tax;
    }
@endphp
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 11mm 11mm 15mm; }
        body { color:#1f2937; font-family: sans-serif; font-size:9.5pt; line-height:1.38; }
        h1,h2,p { margin-top:0; } h1 { color:#0f4c81; font-size:21pt; margin-bottom:3px; }
        h2 { color:#0f4c81; font-size:12pt; margin:15px 0 6px; border-bottom:1px solid #cbd5e1; padding-bottom:4px; }
        table { border-collapse:collapse; width:100%; } td,th { vertical-align:top; }
        .header { border-bottom:3px solid #0f6aa6; padding-bottom:8px; }
        .header td { width:50%; } .right { text-align:right; } .muted { color:#64748b; }
        .status { display:inline-block; background:#e2e8f0; border-radius:10px; padding:2px 7px; text-transform:uppercase; font-size:7.5pt; }
        .two-column { margin-top:12px; } .two-column td { border:1px solid #dbe3ea; padding:8px; width:50%; }
        .stay-grid { table-layout:fixed; } .stay-grid td { border:1px solid #dbe3ea; padding:7px; }
        .stay-grid strong { display:block; color:#475569; font-size:8pt; text-transform:uppercase; letter-spacing:.25px; }
        .lines th { color:#fff; background:#0f6aa6; padding:7px; text-align:left; }
        .lines td { border:1px solid #dbe3ea; padding:7px; }
        .totals { width:48%; margin-left:52%; margin-top:9px; }
        .totals td { border-bottom:1px solid #dbe3ea; padding:5px 7px; }
        .totals .grand { color:#0f4c81; font-size:12pt; font-weight:bold; }
        .deposit { border:1px solid #38bdf8; background:#f0f9ff; padding:9px; margin-top:13px; page-break-inside:avoid; }
        .payment { page-break-inside:avoid; } .payment th,.payment td { border:1px solid #dbe3ea; padding:6px; }
        .payment th { background:#eaf3f8; }
        .terms { border:1px solid #dbe3ea; background:#f8fafc; padding:9px; page-break-inside:avoid; }
        .footer { border-top:1px solid #cbd5e1; color:#64748b; font-size:8pt; margin-top:16px; padding-top:6px; text-align:center; page-break-inside:avoid; }
        .nowrap { white-space:nowrap; }
    </style>
</head>
<body>
<table class="header"><tr>
    <td>
        <strong style="font-size:16pt">{{ $business->name }}</strong><br>
        @if(data_get($pdfSettings, 'address')){!! nl2br(e(data_get($pdfSettings, 'address'))) !!}<br>@endif
        @if(data_get($pdfSettings, 'phone')){{ data_get($pdfSettings, 'phone') }}@endif
        @if(data_get($pdfSettings, 'email')) · {{ data_get($pdfSettings, 'email') }}@endif
        @if(data_get($pdfSettings, 'website'))<br>{{ data_get($pdfSettings, 'website') }}@endif
    </td>
    <td class="right">
        <h1>{{ $documentContext['title'] }}</h1>
        <strong>{{ $transaction->invoice_no ?: $transaction->ref_no }}</strong><br>
        <span class="status">{{ $transaction->status }}</span><br><br>
        Issue date: {{ \Carbon\Carbon::parse($documentContext['booking_date'])->format('d M Y') }}
    </td>
</tr></table>

<table class="two-column"><tr>
    <td><strong>Accommodation provider</strong><br>{{ $business->name }}<br><span class="muted">Booking reference: {{ $transaction->ref_no }}</span></td>
    <td><strong>Guest / customer</strong><br>{{ optional($transaction->contact)->name ?: 'Not assigned' }}
        @if(optional($transaction->contact)->business_name)<br>{{ $transaction->contact->business_name }}@endif
        @if(optional($transaction->contact)->mobile)<br>{{ $transaction->contact->mobile }}@endif
        @if(optional($transaction->contact)->email)<br>{{ $transaction->contact->email }}@endif
    </td>
</tr></table>

<h2>Stay details</h2>
<table class="stay-grid">
    <tr>
        <td><strong>Booking date</strong>{{ \Carbon\Carbon::parse($documentContext['booking_date'])->format('d M Y') }}</td>
        <td><strong>Arrival</strong>{{ \Carbon\Carbon::parse($documentContext['arrival_date'])->format('d M Y') }} at {{ $documentContext['arrival_time'] }}</td>
        <td><strong>Checkout</strong>{{ \Carbon\Carbon::parse($documentContext['checkout_date'])->format('d M Y') }} at {{ $documentContext['checkout_time'] }}</td>
        <td><strong>Billable stay</strong>{{ $documentContext['number_of_days'] }} {{ $documentContext['number_of_days'] === 1 ? 'night' : 'nights' }}</td>
    </tr>
    <tr><td colspan="4"><strong>Stay duration</strong>{{ $documentContext['stay_duration'] }}</td></tr>
</table>

<h2>{{ $isQuotation ? 'Quoted accommodation and services' : 'Accommodation charges' }}</h2>
<table class="lines">
    <thead><tr><th>Accommodation / service</th><th class="right">Guests</th><th class="right nowrap">Rate / night</th><th class="right">Amount</th></tr></thead>
    <tbody>
    @foreach($booking_rooms as $room)
        <tr>
            <td>{{ $room->type }} · Room {{ $room->room_number }}</td>
            <td class="right">{{ (int)$room->adults }} adult(s)@if((int)$room->childrens > 0), {{ (int)$room->childrens }} child(ren)@endif</td>
            <td class="right">{{ $currencyCode }} {{ number_format((float)$room->price, 2) }}</td>
            <td class="right">{{ $currencyCode }} {{ number_format((float)$room->total_price, 2) }}</td>
        </tr>
    @endforeach
    @foreach($revenueExtras as $bookingExtra)
        <tr><td>{{ optional($bookingExtra->extra)->name ?: 'Guest service' }}</td><td class="right">—</td><td class="right">—</td><td class="right">{{ $currencyCode }} {{ number_format((float)$bookingExtra->price, 2) }}</td></tr>
    @endforeach
    </tbody>
</table>

<table class="totals">
    <tr><td>Accommodation</td><td class="right">{{ $currencyCode }} {{ number_format((float)$transaction->room_price, 2) }}</td></tr>
    @if((float)$transaction->extra_price !== 0.0)<tr><td>Chargeable services</td><td class="right">{{ $currencyCode }} {{ number_format((float)$transaction->extra_price, 2) }}</td></tr>@endif
    @if($discountValue > 0)<tr><td>Discount</td><td class="right">− {{ $currencyCode }} {{ number_format($discountValue, 2) }}</td></tr>@endif
    @if((float)$transaction->tax_amount !== 0.0)<tr><td>Tax</td><td class="right">{{ $currencyCode }} {{ number_format((float)$transaction->tax_amount, 2) }}</td></tr>@endif
    <tr class="grand"><td>{{ $isQuotation ? 'Quoted total' : 'Invoice total' }}</td><td class="right">{{ $currencyCode }} {{ number_format((float)$transaction->final_total, 2) }}</td></tr>
    @if($documentContext['show_payment_summary'] && (float)$transaction->total_paid > 0)
        <tr><td>Payment Deposit / Paid</td><td class="right">{{ $currencyCode }} {{ number_format((float)$transaction->total_paid, 2) }}</td></tr>
        <tr><td>Balance due</td><td class="right">{{ $currencyCode }} {{ number_format(max(0, (float)$transaction->final_total - (float)$transaction->total_paid), 2) }}</td></tr>
    @endif
</table>

@if($securityDeposit && (float)$securityDeposit->required_amount > 0)
    <div class="deposit"><strong>Refundable security deposit — {{ $currencyCode }} {{ number_format((float)$securityDeposit->required_amount, 2) }}</strong><br>
        <span class="muted">Held separately against loss or damage. It is not accommodation income and is excluded from tax, invoice total, payments and balance due.</span>
        @if(!$isQuotation && (float)$securityDeposit->received_amount > 0)<br>Currently held: {{ $currencyCode }} {{ number_format((float)$securityDeposit->held_balance, 2) }} · Status: {{ ucfirst(str_replace('_', ' ', $securityDeposit->status)) }}@endif
    </div>
@endif

@if($documentContext['show_payment_lines'] && $transaction->payment_lines->isNotEmpty())
    <h2>Payments applied to this accommodation</h2>
    <table class="payment"><thead><tr><th>Date</th><th>Reference</th><th>Method</th><th class="right">Amount</th></tr></thead><tbody>
        @foreach($transaction->payment_lines as $payment)
            <tr><td>{{ \Carbon\Carbon::parse($payment->paid_on)->format('d M Y') }}</td><td>{{ $payment->payment_ref_no ?: '—' }}</td><td>{{ $payment_types[$payment->method] ?? ucfirst(str_replace('_',' ',$payment->method)) }}</td><td class="right">{{ $payment->is_return ? '− ' : '' }}{{ $currencyCode }} {{ number_format((float)$payment->amount, 2) }}</td></tr>
        @endforeach
    </tbody></table>
@endif

@if(data_get($pdfSettings, 'text_after_table'))
    <h2>{{ $isQuotation ? 'Quotation notes' : 'Payment and stay information' }}</h2>
    <div class="terms">{!! strip_tags((string)data_get($pdfSettings, 'text_after_table'), '<br><strong><b><em><i><ul><ol><li><p>') !!}</div>
@endif

<div class="footer">
    @if(data_get($pdfSettings, 'footer_text')){!! strip_tags((string)data_get($pdfSettings, 'footer_text'), '<br><strong><b><em><i><p>') !!}<br>@endif
    Generated by CashERP · {{ $documentContext['title'] }} · {{ $transaction->ref_no }}
</div>
</body>
</html>
