@extends('layouts.app')
@section('title', $model->folio_number)
@section('content')
@include('hms::layouts.nav')
<section class="content-header"><h1>{{ $model->folio_number }} <small>{{ optional($model->contact)->name ?: optional($model->groupBooking)->name }}@if($model->eventBooking) · {{ $model->eventBooking->event_name }}@endif — {{ optional($model->property)->name }}</small></h1></section>
<section class="content">
@if(app(\App\Services\FeatureAccessService::class)->enabled('smart_documents') && (auth()->user()->can('smart_documents.create') || auth()->user()->hasRole('Admin#'.session('user.business_id'))))
    <div class="tw-mb-3"><a class="btn btn-info" href="{{ route('smart-documents.create', ['source_type' => 'hms_folio', 'source_id' => $model->id]) }}"><i class="fa fa-file-text-o"></i> Create {{ $model->eventBooking ? 'event' : 'guest folio' }} document</a></div>
@endif

<div class="row">
    <div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-blue"><i class="fa fa-file-text-o"></i></span><div class="info-box-content"><span class="info-box-text">@lang('hms::lang.balance')</span><span class="info-box-number display_currency" data-currency_symbol="true">{{ $model->balance }}</span></div></div></div>
    <div class="col-md-3"><div class="info-box"><span class="info-box-icon {{ $model->status === 'settled' ? 'bg-green' : 'bg-yellow' }}"><i class="fa fa-check-circle"></i></span><div class="info-box-content"><span class="info-box-text">@lang('hms::lang.status')</span><span class="info-box-number">{{ ucfirst($model->status) }}</span></div></div></div>
</div>

<div class="row">
    <div class="col-md-6"><div class="callout callout-info"><h4>Payment Deposit (Advance)</h4><p>A real advance toward accommodation or services. It reduces the guest balance and follows normal payment and accounting treatment.</p></div></div>
    <div class="col-md-6"><div class="callout callout-warning"><h4>Security Deposit (Refundable)</h4><p>Held against loss or damage. It is tracked separately and does not change folio revenue, tax, invoice payment, or accounting totals.</p></div></div>
</div>

<div class="box box-primary"><div class="box-header with-border"><h3 class="box-title">@lang('hms::lang.post_folio_entry')</h3></div><div class="box-body">
{!! Form::open(['route' => ['hms.folios.entries.store', $model->id], 'class' => 'form-inline']) !!}
    {!! Form::select('entry_type', ['charge' => __('hms::lang.charge'), 'payment' => __('hms::lang.payment'), 'payment_deposit' => 'Payment Deposit (Advance)', 'refund' => __('hms::lang.refund'), 'adjustment' => __('hms::lang.adjustment')], null, ['class' => 'form-control', 'required']) !!}
    {!! Form::select('category', ['room_charge' => __('hms::lang.room_charge'), 'restaurant' => __('hms::lang.restaurant'), 'minibar' => __('hms::lang.minibar'), 'laundry' => __('hms::lang.laundry'), 'tax' => __('hms::lang.tax'), 'fee' => __('hms::lang.fee'), 'payment' => __('hms::lang.payment'), 'booking_payment' => 'Booking payment', 'refund' => __('hms::lang.refund'), 'adjustment' => __('hms::lang.adjustment'), 'other' => __('hms::lang.other')], null, ['class' => 'form-control', 'required']) !!}
    {!! Form::select('direction', ['debit' => __('hms::lang.debit'), 'credit' => __('hms::lang.credit')], null, ['class' => 'form-control', 'required']) !!}
    {!! Form::number('amount', null, ['class' => 'form-control', 'required', 'min' => 0.0001, 'step' => '0.0001', 'placeholder' => __('hms::lang.amount')]) !!}
    {!! Form::select('payment_method', $paymentMethods, null, ['class' => 'form-control', 'placeholder' => __('hms::lang.payment_method')]) !!}
    {!! Form::text('description', null, ['class' => 'form-control', 'required', 'placeholder' => __('hms::lang.description')]) !!}
    <button class="btn btn-primary">@lang('hms::lang.post')</button>
{!! Form::close() !!}
<p class="help-block">A payment method is required for payments, payment deposits and refunds. Credit and Free/Complimentary remain booking arrangements; they cannot be recorded as money received.</p>
</div></div>

<div class="box box-default"><div class="box-header with-border"><h3 class="box-title">@lang('hms::lang.folio_ledger')</h3></div><div class="box-body table-responsive"><table class="table table-bordered table-striped">
<thead><tr><th>@lang('hms::lang.posted_at')</th><th>@lang('hms::lang.type')</th><th>@lang('hms::lang.description')</th><th>@lang('hms::lang.debit')</th><th>@lang('hms::lang.credit')</th><th>@lang('hms::lang.status')</th><th>@lang('messages.action')</th></tr></thead>
<tbody>
@forelse($model->entries as $entry)
    <tr class="{{ $entry->status === 'void' ? 'text-muted' : '' }}">
        <td>{{ @format_datetime($entry->posted_at) }}<br><small>{{ optional($entry->business_date)->toDateString() }}</small></td>
        <td>{{ $entry->entry_type === 'payment_deposit' ? 'Payment Deposit (Advance)' : ucfirst($entry->entry_type) }}<br><small>{{ ucfirst(str_replace('_', ' ', $entry->category)) }}</small></td>
        <td>{{ $entry->description }}</td>
        <td>{{ $entry->direction === 'debit' ? @num_format($entry->amount) : '-' }}</td><td>{{ $entry->direction === 'credit' ? @num_format($entry->amount) : '-' }}</td>
        <td><span class="label {{ in_array($entry->status, ['approved', 'posted']) ? 'bg-green' : ($entry->status === 'pending' ? 'bg-yellow' : 'bg-gray') }}">{{ ucfirst($entry->status) }}</span></td>
        <td>
            @if($entry->entry_type === 'refund' && $entry->status === 'pending' && (auth()->user()->can('superadmin') || auth()->user()->can('hms.approve_refunds'))){!! Form::open(['route' => ['hms.folios.refunds.approve', $entry->id], 'method' => 'post', 'style' => 'display:inline']) !!}<button class="btn btn-xs btn-success">@lang('hms::lang.approve')</button>{!! Form::close() !!}@endif
            @if(in_array($entry->status, ['posted', 'approved']) && $entry->category !== 'transfer' && $transferTargets->isNotEmpty())<button class="btn btn-xs btn-info" data-toggle="collapse" data-target="#transfer-{{ $entry->id }}">@lang('hms::lang.transfer')</button>@endif
            @if($entry->status !== 'void')<button class="btn btn-xs btn-danger" data-toggle="collapse" data-target="#void-{{ $entry->id }}">@lang('hms::lang.void')</button>@endif
        </td>
    </tr>
    @if(in_array($entry->status, ['posted', 'approved']) && $entry->category !== 'transfer' && $transferTargets->isNotEmpty())
        <tr id="transfer-{{ $entry->id }}" class="collapse"><td colspan="7">{!! Form::open(['route' => ['hms.folios.entries.transfer', $entry->id], 'method' => 'post', 'class' => 'form-inline']) !!}{!! Form::hidden('idempotency_token', (string) Str::uuid()) !!}{!! Form::select('target_folio_id', $transferTargets, null, ['class' => 'form-control select2', 'required', 'placeholder' => __('hms::lang.target_folio')]) !!}{!! Form::number('amount', $entry->amount, ['class' => 'form-control', 'required', 'min' => 0.0001, 'max' => $entry->amount, 'step' => '0.0001']) !!}<button class="btn btn-info">@lang('hms::lang.confirm_transfer')</button>{!! Form::close() !!}</td></tr>
    @endif
    @if($entry->status !== 'void')
        <tr id="void-{{ $entry->id }}" class="collapse"><td colspan="7">{!! Form::open(['route' => ['hms.folios.entries.void', $entry->id], 'method' => 'post', 'class' => 'form-inline']) !!}{!! Form::text('reason', null, ['class' => 'form-control', 'required', 'minlength' => 3, 'placeholder' => __('hms::lang.void_reason')]) !!}<button class="btn btn-danger">@lang('hms::lang.confirm_void')</button>{!! Form::close() !!}</td></tr>
    @endif
@empty
    <tr><td colspan="7" class="text-center">@lang('hms::lang.no_folio_entries')</td></tr>
@endforelse
</tbody></table></div></div>

<div class="box box-info"><div class="box-header with-border"><h3 class="box-title">Payment Deposit (Advance) Schedule</h3></div><div class="box-body">
    {!! Form::open(['route' => ['hms.folios.deposits.store', $model->id], 'class' => 'form-inline']) !!}{!! Form::number('amount', null, ['class' => 'form-control', 'required', 'min' => 0.0001, 'step' => '0.0001', 'placeholder' => __('hms::lang.amount')]) !!}{!! Form::date('due_date', null, ['class' => 'form-control', 'required']) !!}{!! Form::text('notes', null, ['class' => 'form-control', 'placeholder' => __('hms::lang.notes')]) !!}<button class="btn btn-primary">Schedule advance</button>{!! Form::close() !!}
    <p class="help-block">A schedule requests an advance toward the booking price. It is not the refundable security-deposit register.</p><hr>
    <ul>@forelse($model->deposits as $deposit)<li>{{ optional($deposit->due_date)->toDateString() }} — {{ @num_format($deposit->amount) }} — {{ ucfirst($deposit->status) }} (Payment Deposit)</li>@empty<li>No payment deposits scheduled.</li>@endforelse</ul>
</div></div>

@php
    $canManageSecurity = auth()->user()->can('superadmin') || auth()->user()->can('hms.manage_security_deposits') || auth()->user()->hasRole('Admin#'.session('user.business_id'));
    $canApproveSecurity = auth()->user()->can('superadmin') || auth()->user()->can('hms.approve_security_deposit_refunds') || auth()->user()->hasRole('Admin#'.session('user.business_id'));
    $contextTitle = $model->eventBooking
        ? $model->folio_number.' / event '.$model->eventBooking->event_number
        : $model->folio_number.' / booking '.(optional($model->booking)->invoice_no ?: '#'.$model->transaction_id);
    $defaultRequired = 0;
    $depositRoutes = [
        'requirement' => route('hms.folios.security_deposit.requirement', $model->id),
        'receipt' => route('hms.folios.security_deposit.receipt', $model->id),
        'damage' => route('hms.folios.security_deposit.damage', $model->id),
        'refund' => route('hms.folios.security_deposit.refund', $model->id),
        'approve' => url('/hms/security-deposit-refunds/{entry}/approve'),
        'pay' => url('/hms/security-deposit-refunds/{entry}/pay'),
        'void' => url('/hms/security-deposit-entries/{entry}/void'),
        'waive' => route('hms.folios.security_deposit.waive', $model->id),
    ];
@endphp
@include('security_deposits.panel')
</section>
@endsection
