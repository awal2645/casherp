@php
    $business = $document->business_snapshot ?: [];
    $party = $document->party_snapshot ?: [];
    $template = $document->template_snapshot ?: [];
    $currencyCode = data_get($template, 'currency_code', optional($document->currency)->code ?: '');
    $fields = (array) data_get($template, 'fields', []);
    $isAgreement = (bool) data_get($template, 'requires_acceptance', false);
    $typeCode = optional($document->type)->code;
    $isFinancial = (bool) optional($document->type)->is_financial;
    $showPayments = $isFinancial && !in_array($typeCode, ['standard_quotation', 'proforma_invoice', 'room_booking_quotation', 'event_quotation', 'catering_quotation'], true);
    if (!$isFinancial) {
        $fields = collect($fields)->reject(fn ($field) => in_array($field['key'] ?? null, [
            'payment_deposit_amount', 'payment_date', 'payment_method', 'payment_reference',
        ], true))->values()->all();
    }
    $detailsHeading = [
        'event' => 'Event details', 'catering' => 'Event and catering details',
        'rental' => 'Property and lease details', 'security_deposit' => 'Property and deposit details',
        'short_stay' => 'Stay details', 'long_stay' => 'Stay and occupancy details',
        'professional_service' => 'Engagement details', 'retail_order' => 'Order and delivery details',
        'construction_project' => 'Project and site details', 'healthcare_service' => 'Patient billing details',
        'education_fee' => 'Student and academic details', 'automotive_service' => 'Vehicle and service details',
        'logistics_shipment' => 'Shipment details', 'donation_membership' => 'Supporter and programme details',
        'payment' => 'Payment details',
    ][$document->scenario_code] ?? 'Document details';
@endphp
<!doctype html>
<html><head><meta charset="utf-8"><style>
    @page { margin: 12mm 11mm 15mm; }
    body { font-family: sans-serif; color:#1f2937; font-size:10pt; line-height:1.4; }
    h1,h2,h3,p { margin-top:0; } h1 { color:#0f4c81; font-size:22pt; margin-bottom:4px; } h2 { color:#0f4c81; font-size:13pt; border-bottom:1px solid #cbd5e1; padding-bottom:4px; margin:18px 0 8px; }
    .muted { color:#64748b; } .right { text-align:right; } .center { text-align:center; } .nowrap { white-space:nowrap; }
    .header { width:100%; border-bottom:3px solid #0f6aa6; padding-bottom:10px; } .header td { vertical-align:top; }
    .party { width:100%; margin-top:14px; } .party td { width:50%; vertical-align:top; padding:8px; border:1px solid #dbe3ea; }
    table.grid { width:100%; border-collapse:collapse; margin-top:8px; } table.grid th { background:#0f6aa6; color:#fff; padding:7px; text-align:left; } table.grid td { border:1px solid #dbe3ea; padding:7px; vertical-align:top; }
    table.details { width:100%; border-collapse:collapse; } table.details td { padding:5px 7px; border-bottom:1px solid #e5e7eb; } table.details td.label { width:30%; color:#475569; font-weight:bold; }
    .totals { width:45%; margin-left:55%; margin-top:10px; border-collapse:collapse; } .totals td { padding:5px; border-bottom:1px solid #dbe3ea; } .totals .grand { font-size:12pt; font-weight:bold; color:#0f4c81; }
    .status { display:inline-block; padding:3px 8px; background:#e2e8f0; border-radius:10px; text-transform:uppercase; font-size:8pt; letter-spacing:.4px; }
    .terms { white-space:pre-wrap; text-align:justify; } .signatures { width:100%; margin-top:24px; } .signatures td { width:50%; padding:10px 12px 22px 0; vertical-align:top; } .signature-line { border-top:1px solid #334155; margin-top:28px; padding-top:4px; }
    .footer { margin-top:16px; text-align:center; color:#64748b; font-size:8pt; border-top:1px solid #e2e8f0; padding-top:4px; page-break-inside:avoid; }
</style></head><body>
<table class="header"><tr><td>
    <div class="muted">{{ data_get($business, 'industry') }}</div>
    <strong style="font-size:16pt">{{ data_get($business, 'name') }}</strong><br>
    {{ data_get($business, 'location_name') }}<br>{{ data_get($business, 'address') }}<br>
    {{ data_get($business, 'mobile') }} @if(data_get($business, 'email')) · {{ data_get($business, 'email') }} @endif
    @if(data_get($business, 'tax_number_1'))<br>{{ data_get($business, 'tax_label_1', 'Tax number') }}: {{ data_get($business, 'tax_number_1') }}@endif
</td><td class="right">
    <h1>{{ $document->title }}</h1>
    <strong>{{ $document->document_number }}</strong><br><span class="status">{{ $document->status }}</span><br><br>
    Issue date: {{ optional($document->issue_date)->format('d M Y') }}
    @if($document->valid_until)<br>Valid / due until: {{ $document->valid_until->format('d M Y') }}@endif
</td></tr></table>

<table class="party"><tr><td><strong>Issued by</strong><br>{{ data_get($business, 'name') }}<br>{{ data_get($business, 'address') }}</td><td><strong>Issued to</strong><br>{{ data_get($party, 'business_name') ?: data_get($party, 'name', 'Not assigned') }}<br>{{ data_get($party, 'address') }}<br>{{ data_get($party, 'mobile') }} @if(data_get($party, 'email')) · {{ data_get($party, 'email') }} @endif @if(data_get($party, 'tax_number'))<br>Tax number: {{ data_get($party, 'tax_number') }}@endif</td></tr></table>

@if($document->subject)<p style="margin-top:12px"><strong>Subject:</strong> {{ $document->subject }}</p>@endif
@if($document->service_start_at || $document->service_end_at)<p><strong>Service / occupancy:</strong> {{ optional($document->service_start_at)->format('d M Y H:i') ?: '—' }} to {{ optional($document->service_end_at)->format('d M Y H:i') ?: '—' }}</p>@endif

@if(collect($fields)->contains(fn ($field) => filled(data_get($document->data, $field['key']))))
    <h2>{{ $detailsHeading }}</h2>
    <table class="details">@foreach($fields as $field)@if(filled(data_get($document->data, $field['key'])))<tr><td class="label">{{ $field['label'] }}</td><td>{!! nl2br(e(data_get($document->data, $field['key']))) !!}</td></tr>@endif @endforeach</table>
@endif

<h2>{{ $document->scenario_code === 'security_deposit' ? 'Security-deposit details' : ($document->scenario_code === 'payment' ? 'Payment details' : 'Charges and services') }}</h2>
<table class="grid"><thead><tr><th>Description</th><th class="right nowrap">Qty</th><th class="right nowrap">Unit price</th><th class="right">Discount</th><th class="right">Tax</th><th class="right">Total</th></tr></thead><tbody>
@foreach($document->lines as $line)<tr><td>{{ $line->description }}@if($line->line_type !== 'item')<br><small class="muted">{{ ucfirst($line->line_type) }}</small>@endif</td><td class="right">{{ number_format($line->quantity, 2) }}</td><td class="right">{{ number_format($line->unit_price, 2) }}</td><td class="right">{{ number_format($line->discount_amount, 2) }}</td><td class="right">{{ number_format($line->tax_amount, 2) }}</td><td class="right">{{ number_format($line->line_total, 2) }}</td></tr>@endforeach
</tbody></table>
<table class="totals"><tr><td>Subtotal</td><td class="right">{{ $currencyCode }} {{ number_format($document->subtotal, 2) }}</td></tr>@if((float)$document->discount_amount !== 0.0)<tr><td>Discount</td><td class="right">{{ $currencyCode }} {{ number_format($document->discount_amount, 2) }}</td></tr>@endif @if((float)$document->tax_amount !== 0.0)<tr><td>Tax</td><td class="right">{{ $currencyCode }} {{ number_format($document->tax_amount, 2) }}</td></tr>@endif<tr class="grand"><td>{{ $isFinancial ? 'Total' : 'Quoted total' }}</td><td class="right">{{ $currencyCode }} {{ number_format($document->total_amount, 2) }}</td></tr>@if($showPayments && (float)$document->amount_paid !== 0.0)<tr><td>Payment Deposit / Paid</td><td class="right">{{ $currencyCode }} {{ number_format($document->amount_paid, 2) }}</td></tr><tr><td>Balance</td><td class="right">{{ $currencyCode }} {{ number_format($document->balance_due, 2) }}</td></tr>@endif</table>

@if(in_array($document->scenario_code, ['short_stay', 'long_stay', 'event'], true) && (float)data_get($document->data, 'security_deposit', 0) > 0)
<div style="margin-top:14px;padding:9px;border:1px solid #7dd3fc;background:#f0f9ff;page-break-inside:avoid"><strong>Refundable security deposit:</strong> {{ $currencyCode }} {{ number_format((float)data_get($document->data, 'security_deposit'), 2) }}<br><span class="muted">Held separately for loss or damage. It is excluded from revenue, tax, the document total and the normal amount due.</span></div>
@endif

@if($document->notes)<h2>Notes</h2><div class="terms">{{ $document->notes }}</div>@endif
@if($document->terms)<h2>Terms and conditions</h2><div class="terms">{{ $document->terms }}</div>@endif

@if($document->signatories->isNotEmpty())
    <h2>{{ $isAgreement ? 'Agreement and signatures' : 'Authorization' }}</h2>
    <table class="signatures">@foreach($document->signatories->chunk(2) as $pair)<tr>@foreach($pair as $signatory)<td><strong>{{ $signatory->role }}</strong><br>{{ $signatory->name }} @if($signatory->position)<br>{{ $signatory->position }}@endif<div class="signature-line">Signature / date</div></td>@endforeach @if($pair->count() === 1)<td></td>@endif</tr>@endforeach</table>
@endif

<div class="footer">Generated by CashERP · {{ $document->document_number }} · This system record is subject to the issuing company’s approved terms and applicable law.</div>
</body></html>
