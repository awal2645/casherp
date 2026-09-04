<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title>{{ $document->title }} {{ $document->document_number }}</title><style>
body{margin:0;background:#f4f7fa;color:#1f2937;font-family:Arial,sans-serif}.bar{background:#0f4c81;color:#fff;padding:18px}.bar-inner,.card{max-width:980px;margin:auto}.card{background:#fff;margin-top:24px;margin-bottom:24px;border:1px solid #dbe3ea;border-radius:6px;box-shadow:0 4px 18px rgba(15,76,129,.08);padding:28px}.button{display:inline-block;background:#0f6aa6;color:#fff;text-decoration:none;padding:10px 16px;border:0;border-radius:4px;cursor:pointer;margin:0 0 5px 4px}.muted{color:#64748b}.grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}.items,.details{width:100%;border-collapse:collapse;margin-top:20px}.items th,.items td,.details th,.details td{border:1px solid #dbe3ea;padding:9px;text-align:left}.items th,.details th{background:#eef4f8}.details th{width:34%}.right{text-align:right!important}@media(max-width:680px){.grid{grid-template-columns:1fr}.card{margin:10px;padding:18px}.items,.details{font-size:12px}.button{float:none!important;margin-bottom:10px}}
</style></head><body>
<div class="bar"><div class="bar-inner"><strong>CashERP Secure Document</strong></div></div>
<main class="card">
    <div style="float:right"><a class="button" href="{{ route('public.smart-document.pdf', ['token' => $token]) }}" target="_blank" rel="noopener">Preview</a><a class="button" href="{{ route('public.smart-document.print', ['token' => $token]) }}" target="_blank" rel="noopener">Print</a><a class="button" href="{{ route('public.smart-document.download', ['token' => $token]) }}">Download PDF</a><button class="button" type="button" id="share_document">Share</button></div>
    <p class="muted">{{ data_get($document->business_snapshot, 'industry') }}</p><h1>{{ $document->title }}</h1><p><strong>{{ $document->document_number }}</strong> · {{ ucfirst($document->status) }} · {{ optional($document->issue_date)->format('d M Y') }}</p><hr>
    <div class="grid"><div><h3>Issued by</h3><p>{{ data_get($document->business_snapshot, 'name') }}<br>{{ data_get($document->business_snapshot, 'address') }}</p></div><div><h3>Issued to</h3><p>{{ data_get($document->party_snapshot, 'business_name') ?: data_get($document->party_snapshot, 'name', 'Not assigned') }}<br>{{ data_get($document->party_snapshot, 'address') }}</p></div></div>
    @if($document->subject)<p><strong>Subject:</strong> {{ $document->subject }}</p>@endif
    @if(collect((array) data_get($document->template_snapshot, 'fields', []))->contains(fn ($field) => filled(data_get($document->data, $field['key']))))
        <h3>Document details</h3><table class="details"><tbody>@foreach((array) data_get($document->template_snapshot, 'fields', []) as $field)@if(filled(data_get($document->data, $field['key'])))<tr><th>{{ $field['label'] }}</th><td>{!! nl2br(e(data_get($document->data, $field['key']))) !!}</td></tr>@endif @endforeach</tbody></table>
    @endif
    <div style="overflow:auto"><table class="items"><thead><tr><th>Description</th><th class="right">Qty</th><th class="right">Total</th></tr></thead><tbody>@foreach($document->lines as $line)<tr><td>{{ $line->description }}</td><td class="right">{{ number_format($line->quantity, 2) }}</td><td class="right">{{ data_get($document->template_snapshot, 'currency_code') }} {{ number_format($line->line_total, 2) }}</td></tr>@endforeach</tbody><tfoot><tr><th colspan="2" class="right">Total</th><th class="right">{{ data_get($document->template_snapshot, 'currency_code') }} {{ number_format($document->total_amount, 2) }}</th></tr>@if((float)$document->amount_paid !== 0.0)<tr><th colspan="2" class="right">Paid</th><th class="right">{{ data_get($document->template_snapshot, 'currency_code') }} {{ number_format($document->amount_paid, 2) }}</th></tr><tr><th colspan="2" class="right">Balance</th><th class="right">{{ data_get($document->template_snapshot, 'currency_code') }} {{ number_format($document->balance_due, 2) }}</th></tr>@endif</tfoot></table></div>
    @if($document->notes)<h3>Notes</h3><div style="white-space:pre-wrap">{{ $document->notes }}</div>@endif
    @if($document->terms)<h3>Terms and conditions</h3><div style="white-space:pre-wrap">{{ $document->terms }}</div>@endif
    @if($document->signatories->isNotEmpty())<h3>Authorized parties</h3><div class="grid">@foreach($document->signatories as $signatory)<div><strong>{{ $signatory->role }}</strong><br>{{ $signatory->name ?: 'Signature pending' }}@if($signatory->position)<br><span class="muted">{{ $signatory->position }}</span>@endif</div>@endforeach</div>@endif
    <p class="muted" style="margin-top:28px">This read-only link expires automatically and can be revoked by the issuing company. Contact the issuer if any information appears incorrect.</p>
</main><script nonce="{{ $scriptNonce }}">
(function () {
    var button = document.getElementById('share_document');
    if (!button) return;
    button.addEventListener('click', function () {
        if (navigator.share) {
            navigator.share({title: @json($document->title.' '.$document->document_number), url: window.location.href});
            return;
        }
        if (navigator.clipboard) {
            navigator.clipboard.writeText(window.location.href).then(function () { button.textContent = 'Link copied'; });
        }
    });
})();
</script></body></html>
