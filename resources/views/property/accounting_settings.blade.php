@extends('layouts.app')
@section('title', 'Property Accounting Settings')

@section('content')
<section class="content-header">
    <h1>Property Accounting Settings <small>Map Property activity into Accounting</small></h1>
</section>

<section class="content">
    <div class="callout callout-info">
        <p>
            Rent dues post <strong>debit Tenant Receivables / credit Rental Income</strong>.
            Rent receipts post <strong>debit Cash or Bank / credit Tenant Receivables</strong>.
            Payment Deposits (Advances) post <strong>debit Cash or Bank / credit Customer Advances</strong>
            when received, then <strong>debit Customer Advances / credit Tenant Receivables</strong>
            when applied to a rent due.
            Refundable Security Deposits remain in their separate safeguarding register and do not post here.
            Completed maintenance posts <strong>debit Maintenance Expense / credit the selected
            Cash, Bank, or Payable account</strong>.
        </p>
    </div>

    <div class="box box-primary">
        {!! Form::open(['route' => 'property.accounting.settings.save']) !!}
        <div class="box-body row">
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('rental_income_account_id', 'Rental income account:') !!}
                    {!! Form::select(
                        'rental_income_account_id',
                        $incomeAccounts,
                        $mapping->rental_income_account_id,
                        [
                            'class' => 'form-control select2',
                            'placeholder' => 'Select an income account',
                        ]
                    ) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('tenant_receivable_account_id', 'Tenant receivable account:') !!}
                    {!! Form::select(
                        'tenant_receivable_account_id',
                        $assetAccounts,
                        $mapping->tenant_receivable_account_id,
                        [
                            'class' => 'form-control select2',
                            'placeholder' => 'Select an asset account',
                        ]
                    ) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label(
                        'payment_advance_liability_account_id',
                        'Customer advances liability account:'
                    ) !!}
                    {!! Form::select(
                        'payment_advance_liability_account_id',
                        $liabilityAccounts,
                        $mapping->payment_advance_liability_account_id,
                        [
                            'class' => 'form-control select2',
                            'placeholder' => 'Select a liability account',
                        ]
                    ) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label(
                        'maintenance_expense_account_id',
                        'Maintenance expense account:'
                    ) !!}
                    {!! Form::select(
                        'maintenance_expense_account_id',
                        $expenseAccounts,
                        $mapping->maintenance_expense_account_id,
                        [
                            'class' => 'form-control select2',
                            'placeholder' => 'Select an expense account',
                        ]
                    ) !!}
                </div>
            </div>
            <div class="col-md-12">
                <div class="checkbox">
                    <label>
                        {!! Form::checkbox(
                            'auto_post_enabled',
                            1,
                            $mapping->auto_post_enabled,
                            ['class' => 'input-icheck']
                        ) !!}
                        Enable automatic, balanced Property Accounting posting.
                    </label>
                </div>
                <p class="help-block">
                    All four mappings are required when automatic posting is enabled. Existing
                    historical records are not back-posted automatically.
                </p>
            </div>
        </div>
        <div class="box-footer">
            <a href="{{ route('property.index') }}" class="btn btn-default">Back</a>
            <button class="btn btn-primary pull-right">Save mapping</button>
        </div>
        {!! Form::close() !!}
    </div>
</section>
@endsection
