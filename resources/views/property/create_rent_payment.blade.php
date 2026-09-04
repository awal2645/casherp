@extends('layouts.app')
@section('title', 'Record Rent Payment')

@section('content')
<section class="content-header">
    <h1>Record Rent Payment <small>Receive and allocate tenant rent</small></h1>
</section>

<section class="content">
    <div class="box box-primary">
        <div class="box-body">
            <p>
                <strong>Due:</strong> {{ @format_date($due->due_date) }}
                &nbsp;
                <strong>Balance:</strong>
                <span class="display_currency" data-currency_symbol="true">
                    {{ $due->amount_due - $due->amount_paid }}
                </span>
            </p>
        </div>

        {!! Form::open(['route' => ['property.rents.payment.store', $due]]) !!}
        <div class="box-body row">
            <div class="col-md-4">
                <div class="form-group">
                    {!! Form::label('amount', 'Payment amount:*') !!}
                    {!! Form::text('amount', $due->amount_due - $due->amount_paid, [
                        'class' => 'form-control input_number',
                        'required',
                    ]) !!}
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    {!! Form::label('paid_on', 'Payment date:*') !!}
                    {!! Form::date('paid_on', now()->toDateString(), [
                        'class' => 'form-control',
                        'required',
                    ]) !!}
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    {!! Form::label('method', 'Payment method:*') !!}
                    {!! Form::select('method', [
                        'cash' => 'Cash',
                        'card' => 'Card',
                        'bank_transfer' => 'Bank transfer',
                        'cheque' => 'Cheque',
                        'other' => 'Other',
                    ], 'cash', ['class' => 'form-control', 'required']) !!}
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    {!! Form::label(
                        'receipt_account_id',
                        $autoPost ? 'Cash / bank receipt account:*' : 'Cash / bank receipt account:'
                    ) !!}
                    {!! Form::select('receipt_account_id', $accounts, null, [
                        'class' => 'form-control select2',
                        'placeholder' => 'Select the account receiving this payment',
                        'required' => $autoPost,
                    ]) !!}
                    <p class="help-block">
                        @if($autoPost)
                            Automatic posting is enabled. This account will be debited and tenant
                            receivables will be credited.
                        @else
                            Optional until automatic Property Accounting posting is enabled.
                        @endif
                    </p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    {!! Form::label('reference', 'Reference:') !!}
                    {!! Form::text('reference', null, [
                        'class' => 'form-control',
                        'placeholder' => 'Receipt, bank, cheque, or transaction reference',
                    ]) !!}
                </div>
            </div>
        </div>
        <div class="box-footer">
            <a href="{{ route('property.rents') }}" class="btn btn-default">Cancel</a>
            <button class="btn btn-primary pull-right">Record payment</button>
        </div>
        {!! Form::close() !!}
    </div>
</section>
@endsection
