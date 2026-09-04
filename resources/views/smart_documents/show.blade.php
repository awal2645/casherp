@extends('layouts.app')
@section('title', $document->document_number)

@php
    $isAdmin = auth()->user()->hasRole('Admin#'.session('user.business_id')) || auth()->user()->can('superadmin');
    $transitions = [
        'draft' => ['issued' => 'Issue', 'void' => 'Void'],
        'issued' => ['sent' => 'Mark sent', 'accepted' => 'Mark accepted', 'void' => 'Void'],
        'sent' => ['accepted' => 'Mark accepted', 'void' => 'Void'],
        'accepted' => ['completed' => 'Complete', 'void' => 'Void'],
    ];
    $business = $document->business_snapshot ?: [];
    $party = $document->party_snapshot ?: [];
@endphp

@section('content')
<section class="content-header">
    <h1>{{ $document->title }} <small>{{ $document->document_number }}</small></h1>
</section>
<section class="content">
    @if(session('smart_document_share_url'))
        <div class="alert alert-success">
            <strong>Secure public link created.</strong>
            <div class="input-group tw-mt-2"><input id="share_url" class="form-control" readonly value="{{ session('smart_document_share_url') }}"><span class="input-group-btn"><button type="button" class="btn btn-default" onclick="navigator.clipboard.writeText(document.getElementById('share_url').value)"><i class="fa fa-copy"></i> Copy</button></span></div>
            <small>For security, the full token is displayed only now. Generate a new link if it is lost.</small>
        </div>
    @endif

    <div class="tw-mb-3">
        <a class="btn btn-default" href="{{ route('smart-documents.index') }}"><i class="fa fa-arrow-left"></i> All documents</a>
        <a class="btn btn-primary" target="_blank" rel="noopener" href="{{ route('smart-documents.preview', $document) }}"><i class="fa fa-eye"></i> Preview</a>
        <a class="btn btn-default" target="_blank" rel="noopener" href="{{ route('smart-documents.print', $document) }}"><i class="fa fa-print"></i> Print</a>
        <a class="btn btn-default" href="{{ route('smart-documents.download', $document) }}"><i class="fa fa-download"></i> Download PDF</a>
        @if($document->status === 'draft' && ($isAdmin || auth()->user()->can('smart_documents.update')))
            <a class="btn btn-default" href="{{ route('smart-documents.edit', $document) }}"><i class="fa fa-edit"></i> Edit draft</a>
        @endif
        @if($canConvertTarget && $document->status !== 'void' && ($isAdmin || auth()->user()->can('smart_documents.create')))
            {!! Form::open(['route' => ['smart-documents.convert', $document], 'style' => 'display:inline']) !!}<button class="btn btn-default" onclick="return confirm('Create a linked {{ $convertTarget->name }} draft from this document?')"><i class="fa fa-exchange"></i> Convert to {{ $convertTarget->name }}</button>{!! Form::close() !!}
        @endif
        @if($canCreateReceipt && $document->type->is_financial && !str_contains($document->type->code, 'receipt') && $unreceiptedAmount > 0 && in_array($document->status, ['issued','sent','accepted','completed']) && ($isAdmin || auth()->user()->can('smart_documents.create')))
            <a class="btn btn-success" href="{{ route('smart-documents.create', ['source_type' => 'business_document', 'source_id' => $document->id, 'scenario' => 'payment']) }}"><i class="fa fa-money"></i> Receipt {{ number_format($unreceiptedAmount, 2) }} payment</a>
        @endif
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">Document summary</h3><span class="label {{ $document->status === 'void' ? 'bg-red' : ($document->status === 'draft' ? 'bg-yellow' : 'bg-green') }} pull-right">{{ ucfirst($document->status) }}</span></div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-sm-6"><strong>From</strong><address>{{ data_get($business, 'name') }}<br>{{ data_get($business, 'location_name') }}<br>{{ data_get($business, 'address') }}<br>{{ data_get($business, 'email') }} {{ data_get($business, 'mobile') }}</address></div>
                        <div class="col-sm-6"><strong>To</strong><address>{{ data_get($party, 'business_name') ?: data_get($party, 'name', 'Not assigned') }}<br>{{ data_get($party, 'address') }}<br>{{ data_get($party, 'email') }} {{ data_get($party, 'mobile') }}</address></div>
                    </div>
                    <div class="table-responsive"><table class="table table-bordered"><tbody>
                        <tr><th style="width:25%">Document type</th><td>{{ data_get($document->template_snapshot, 'display_name', optional($document->type)->name) }}</td><th style="width:20%">Issue date</th><td>{{ @format_date($document->issue_date) }}</td></tr>
                        <tr><th>Scenario</th><td>{{ data_get(config('smart_documents.scenarios'), $document->scenario_code.'.name', ucfirst(str_replace('_', ' ', $document->scenario_code))) }}</td><th>Valid / due until</th><td>{{ $document->valid_until ? @format_date($document->valid_until) : '—' }}</td></tr>
                        @if($document->subject)<tr><th>Subject</th><td colspan="3">{{ $document->subject }}</td></tr>@endif
                        @if($document->parent)<tr><th>Linked from</th><td colspan="3"><a href="{{ route('smart-documents.show', $document->parent) }}">{{ $document->parent->document_number }} — {{ $document->parent->title }}</a></td></tr>@endif
                    </tbody></table></div>
                </div>
            </div>

            @if(!empty($document->data))
            <div class="box box-default"><div class="box-header with-border"><h3 class="box-title">Scenario details</h3></div><div class="box-body"><dl class="dl-horizontal">
                @foreach((array) data_get($document->template_snapshot, 'fields', []) as $field)
                    @if(filled(data_get($document->data, $field['key'])))<dt>{{ $field['label'] }}</dt><dd>{!! nl2br(e(data_get($document->data, $field['key']))) !!}</dd>@endif
                @endforeach
            </dl></div></div>
            @endif

            <div class="box box-default"><div class="box-header with-border"><h3 class="box-title">Line items</h3></div><div class="box-body table-responsive"><table class="table table-bordered"><thead><tr><th>Description</th><th class="text-right">Qty</th><th class="text-right">Unit price</th><th class="text-right">Discount</th><th class="text-right">Tax</th><th class="text-right">Total</th></tr></thead><tbody>
                @foreach($document->lines as $line)<tr><td>{{ $line->description }}<br><small class="text-muted">{{ ucfirst($line->line_type) }}</small></td><td class="text-right">{{ number_format($line->quantity, 2) }}</td><td class="text-right">{{ number_format($line->unit_price, 2) }}</td><td class="text-right">{{ number_format($line->discount_amount, 2) }}</td><td class="text-right">{{ number_format($line->tax_amount, 2) }}</td><td class="text-right">{{ number_format($line->line_total, 2) }}</td></tr>@endforeach
            </tbody><tfoot><tr><th colspan="5" class="text-right">Total</th><th class="text-right">{{ number_format($document->total_amount, 2) }}</th></tr><tr><th colspan="5" class="text-right">Paid</th><th class="text-right">{{ number_format($document->amount_paid, 2) }}</th></tr><tr><th colspan="5" class="text-right">Balance</th><th class="text-right">{{ number_format($document->balance_due, 2) }}</th></tr></tfoot></table></div></div>

            @if($document->type->is_financial && !str_contains($document->type->code, 'receipt'))
                <div class="box box-default">
                    <div class="box-header with-border"><h3 class="box-title">Payment ledger</h3><span class="label label-default pull-right">{{ $document->payments->where('status', 'posted')->count() }} posted</span></div>
                    <div class="box-body">
                        <div class="table-responsive"><table class="table table-bordered table-condensed"><thead><tr><th>Date</th><th>Purpose</th><th>Method</th><th>Reference</th><th class="text-right">Amount</th><th>Status</th><th>Action</th></tr></thead><tbody>
                            @forelse($document->payments as $payment)
                                <tr class="{{ $payment->status === 'reversed' ? 'text-muted' : '' }}"><td>{{ @format_date($payment->payment_date) }}</td><td>{{ ($payment->payment_purpose ?? 'invoice_payment') === 'payment_deposit' ? 'Payment Deposit (Advance)' : 'Invoice Payment' }}</td><td>{{ $paymentMethods[$payment->method] ?? ucfirst(str_replace('_', ' ', $payment->method)) }}</td><td>{{ $payment->reference ?: '—' }}</td><td class="text-right">{{ data_get($document->template_snapshot, 'currency_code') }} {{ number_format($payment->amount, 2) }}</td><td>{{ ucfirst($payment->status) }}</td><td>
                                    @if($payment->status === 'posted' && ($isAdmin || auth()->user()->can('smart_documents.payment.reverse')))
                                        {!! Form::open(['route' => ['smart-documents.payments.reverse', $document, $payment], 'style' => 'display:inline']) !!}<input name="reason" class="form-control input-sm" style="width:150px;display:inline-block" required minlength="3" maxlength="255" placeholder="Reversal reason"><button class="btn btn-xs btn-danger" onclick="return confirm('Reverse this payment entry?')">Reverse</button>{!! Form::close() !!}
                                    @elseif($payment->status === 'reversed')
                                        <small>{{ $payment->reversal_reason }}</small>
                                    @endif
                                </td></tr>
                            @empty<tr><td colspan="7" class="text-center text-muted">No payment has been recorded.</td></tr>@endforelse
                        </tbody></table></div>

                        @if((float)$document->balance_due > 0 && in_array($document->status, ['issued','sent','accepted','completed']) && ($isAdmin || auth()->user()->can('smart_documents.payment.create')))
                            <hr><h4>Record payment</h4>
                            {!! Form::open(['route' => ['smart-documents.payments.store', $document], 'class' => 'row']) !!}
                                <div class="col-sm-3 form-group"><label>Date</label><input type="date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" class="form-control" required></div>
                                <div class="col-sm-3 form-group"><label>Amount</label><input type="number" name="amount" value="{{ old('amount', $document->balance_due) }}" step="0.0001" min="0.0001" max="{{ $document->balance_due }}" class="form-control" required></div>
                                <div class="col-sm-3 form-group"><label>Purpose</label><select name="payment_purpose" class="form-control" required><option value="invoice_payment">Invoice payment</option><option value="payment_deposit" @selected(old('payment_purpose') === 'payment_deposit')>Payment Deposit (Advance)</option></select></div>
                                <div class="col-sm-3 form-group"><label>Method</label><select name="method" class="form-control" required>@foreach($paymentMethods as $value=>$label)<option value="{{ $value }}" @selected(old('method') === $value)>{{ $label }}</option>@endforeach</select></div>
                                <div class="col-sm-3 form-group"><label>Reference</label><input name="reference" value="{{ old('reference') }}" class="form-control" maxlength="191" placeholder="Optional unique reference"></div>
                                <div class="col-sm-6 form-group"><label>Notes</label><input name="notes" value="{{ old('notes') }}" class="form-control" maxlength="2000" placeholder="Optional payment note"></div>
                                <div class="col-sm-3 form-group"><label>&nbsp;</label><button class="btn btn-success btn-block"><i class="fa fa-money"></i> Record payment</button></div>
                            {!! Form::close() !!}
                        @endif
                    </div>
                </div>
            @endif

            @if($document->scenario_code === 'event')
                @php
                    $canManageSecurity = $isAdmin || auth()->user()->can('property.deposit.manage') || auth()->user()->can('hms.manage_security_deposits') || auth()->user()->can('smart_documents.payment.create');
                    $canApproveSecurity = $isAdmin || auth()->user()->can('property.deposit.refund') || auth()->user()->can('hms.approve_security_deposit_refunds') || auth()->user()->can('smart_documents.payment.reverse');
                    $contextTitle = $document->document_number.' and its selected venue';
                    $defaultRequired = (float) data_get($document->data, 'security_deposit', 0);
                    $depositRoutes = [
                        'requirement' => route('smart-documents.security-deposit.requirement',$document),
                        'receipt' => route('smart-documents.security-deposit.receipt',$document),
                        'damage' => route('smart-documents.security-deposit.damage',$document),
                        'refund' => route('smart-documents.security-deposit.refund',$document),
                        'approve' => url('/smart-document-security-deposit-refunds/{entry}/approve'),
                        'pay' => url('/smart-document-security-deposit-refunds/{entry}/pay'),
                        'void' => url('/smart-document-security-deposit-entries/{entry}/void'),
                        'waive' => route('smart-documents.security-deposit.waive', $document),
                    ];
                @endphp
                @include('security_deposits.panel')
            @endif

            @if($document->terms || $document->notes)
                <div class="box box-default"><div class="box-header with-border"><h3 class="box-title">Terms and notes</h3></div><div class="box-body">@if($document->terms)<h4>Terms and conditions</h4><div style="white-space:pre-wrap">{{ $document->terms }}</div>@endif @if($document->notes)<hr><h4>Notes</h4><div style="white-space:pre-wrap">{{ $document->notes }}</div>@endif</div></div>
            @endif

            @if($document->signatories->isNotEmpty())
                <div class="box box-default"><div class="box-header with-border"><h3 class="box-title">Signatories</h3></div><div class="box-body row">@foreach($document->signatories as $signatory)<div class="col-sm-6"><p><strong>{{ $signatory->role }}</strong><br>{{ $signatory->name ?: 'Pending signature' }} @if($signatory->position)<br>{{ $signatory->position }}@endif @if($signatory->email)<br>{{ $signatory->email }}@endif</p></div>@endforeach</div></div>
            @endif
        </div>

        <div class="col-md-4">
            <div class="box box-primary"><div class="box-header with-border"><h3 class="box-title">Workflow</h3></div><div class="box-body">
                <p>Current status: <strong>{{ ucfirst($document->status) }}</strong></p>
                @foreach($transitions[$document->status] ?? [] as $next => $label)
                    @php($permission = $next === 'void' ? 'smart_documents.void' : 'smart_documents.issue')
                    @if($isAdmin || auth()->user()->can($permission))
                        {!! Form::open(['route' => ['smart-documents.transition', $document], 'style' => 'display:inline-block;margin:0 4px 6px 0']) !!}<input type="hidden" name="status" value="{{ $next }}"><button class="btn btn-sm {{ $next === 'void' ? 'btn-danger' : 'btn-primary' }}" onclick="return confirm('{{ $next === 'void' ? 'Void this document? The action cannot be reversed.' : 'Move this document to '.ucfirst($next).'?' }}')">{{ $label }}</button>{!! Form::close() !!}
                    @endif
                @endforeach
                @if($document->status === 'draft')<p class="help-block">Issue performs final required-field, line-item, signatory and approved-terms checks.</p>@endif
            </div></div>

            @if(in_array($document->status, ['issued','sent','accepted','completed']) && ($isAdmin || auth()->user()->can('smart_documents.share')))
                <div class="box box-default"><div class="box-header with-border"><h3 class="box-title">Secure sharing</h3></div><div class="box-body">
                    {!! Form::open(['route' => ['smart-documents.share', $document]]) !!}<div class="input-group"><input type="number" min="1" max="365" name="days" value="30" class="form-control" aria-label="Link validity in days"><span class="input-group-btn"><button class="btn btn-primary"><i class="fa fa-share-alt"></i> Create link</button></span></div><p class="help-block">Recipients can securely preview, print and download the issued document until the link expires. Creating a link invalidates any older link.</p>{!! Form::close() !!}
                    @if($document->public_token_hash){!! Form::open(['route' => ['smart-documents.share.revoke', $document], 'method' => 'delete']) !!}<button class="btn btn-xs btn-danger" onclick="return confirm('Revoke the active public link?')">Revoke current link</button>{!! Form::close() !!}@endif
                </div></div>
            @endif

            @if($document->children->isNotEmpty())
                <div class="box box-default"><div class="box-header with-border"><h3 class="box-title">Linked outputs</h3></div><div class="box-body"><ul class="list-unstyled">@foreach($document->children as $child)<li><a href="{{ route('smart-documents.show', $child) }}">{{ $child->document_number }}</a> — {{ $child->title }} <span class="label label-default">{{ $child->status }}</span></li>@endforeach</ul></div></div>
            @endif

            <div class="box box-default"><div class="box-header with-border"><h3 class="box-title">Audit trail</h3></div><div class="box-body"><ul class="timeline timeline-inverse">@foreach($document->events as $event)<li><i class="fa fa-history bg-gray"></i><div class="timeline-item"><span class="time"><i class="fa fa-clock-o"></i> {{ optional($event->occurred_at)->format('Y-m-d H:i') }}</span><h3 class="timeline-header">{{ ucfirst(str_replace('_', ' ', $event->event)) }}</h3>@if($event->from_status || $event->to_status)<div class="timeline-body">{{ $event->from_status ?: 'new' }} → {{ $event->to_status }}</div>@endif</div></li>@endforeach</ul></div></div>
        </div>
    </div>
</section>
@endsection
