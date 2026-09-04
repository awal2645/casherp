@extends('layouts.app')
@section('title', 'Smart Documents')

@section('content')
<section class="content-header">
    <h1>Smart Documents <small>Industry-aware quotations, invoices, receipts and agreements</small></h1>
</section>

<section class="content">
    <div class="tw-mb-3">
        @if($creatableTypes->isNotEmpty() && (auth()->user()->can('smart_documents.create') || auth()->user()->hasRole('Admin#'.session('user.business_id')) || auth()->user()->can('superadmin')))
            <a class="btn btn-primary" href="{{ route('smart-documents.create') }}"><i class="fa fa-plus"></i> Create document</a>
        @endif
        @if(auth()->user()->can('smart_documents.settings') || auth()->user()->hasRole('Admin#'.session('user.business_id')) || auth()->user()->can('superadmin'))
            <a class="btn btn-default" href="{{ route('smart-documents.settings') }}"><i class="fa fa-cog"></i> Document settings</a>
        @endif
    </div>

    @if($types->isEmpty())
        <div class="alert alert-warning"><i class="fa fa-lock"></i> Your current company role does not include any document types. Ask a company administrator to assign the required document types or an explicit user, role, or department access rule.</div>
    @elseif($creatableTypes->isEmpty())
        <div class="alert alert-info"><i class="fa fa-info-circle"></i> You may view document history, but no enabled document type is currently available to you for new creation.</div>
    @endif

    @if(!empty($securityAlerts) && $securityAlerts->isNotEmpty())
        <div class="box box-warning">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-shield"></i> Event security-deposit actions</h3><span class="label label-warning pull-right">{{ $securityAlerts->count() }}</span></div>
            <div class="box-body table-responsive"><table class="table table-condensed">
                <thead><tr><th>Transaction</th><th>Client</th><th>Location</th><th>Reason</th><th>Held balance</th><th>Action</th></tr></thead>
                <tbody>@foreach($securityAlerts as $alert)<tr><td>{{ $alert['transaction_label'] }}<br><small>{{ $alert['transaction_date'] ?: 'No closure date' }}</small></td><td>{{ optional($alert['deposit']->contact)->name ?: 'Not assigned' }}</td><td>{{ optional($alert['deposit']->location)->name ?: 'Not assigned' }}</td><td><span class="label label-{{ $alert['severity'] }}">{{ $alert['reason'] }}</span></td><td><span class="display_currency" data-currency_symbol="true">{{ $alert['deposit']->held_balance }}</span></td><td><a class="btn btn-xs btn-primary" href="{{ route('smart-documents.show', $alert['deposit']->context_id) }}">Open transaction</a></td></tr>@endforeach</tbody>
            </table></div>
        </div>
    @endif

    <div class="box box-primary">
        <div class="box-header with-border"><h3 class="box-title">Find documents</h3></div>
        <div class="box-body">
            {!! Form::open(['route' => 'smart-documents.index', 'method' => 'get', 'class' => 'row']) !!}
                <div class="col-md-3 form-group">
                    {!! Form::label('q', 'Search') !!}
                    {!! Form::text('q', request('q'), ['class' => 'form-control', 'placeholder' => 'Number, title or customer']) !!}
                </div>
                <div class="col-md-3 form-group">
                    {!! Form::label('document_type_id', 'Document type') !!}
                    <select name="document_type_id" class="form-control select2">
                        <option value="">All document types</option>
                        @foreach($types as $type)
                            <option value="{{ $type->id }}" @selected((string) request('document_type_id') === (string) $type->id)>{{ $type->business_display_name ?: ($type->industry_display_name ?: $type->name) }}{{ $type->business_is_enabled ? '' : ' (disabled for new)' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 form-group">
                    {!! Form::label('scenario_code', 'Scenario') !!}
                    <select name="scenario_code" class="form-control">
                        <option value="">All scenarios</option>
                        @foreach($scenarios as $code => $definition)
                            <option value="{{ $code }}" @selected(request('scenario_code') === $code)>{{ $definition['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 form-group">
                    {!! Form::label('status', 'Status') !!}
                    <select name="status" class="form-control">
                        <option value="">All statuses</option>
                        @foreach(['draft', 'issued', 'sent', 'accepted', 'completed', 'void'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 form-group">
                    <label>&nbsp;</label>
                    <div><button class="btn btn-default"><i class="fa fa-search"></i> Filter</button> <a class="btn btn-link" href="{{ route('smart-documents.index') }}">Reset</a></div>
                </div>
            {!! Form::close() !!}
        </div>
    </div>

    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">Documents</h3>
            <span class="label label-default pull-right">{{ $documents->total() }} result{{ $documents->total() === 1 ? '' : 's' }}</span>
        </div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead><tr><th>Number</th><th>Type</th><th>Customer / Client</th><th>Issue date</th><th>Total</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse($documents as $document)
                        <tr>
                            <td><a href="{{ route('smart-documents.show', $document) }}"><strong>{{ $document->document_number }}</strong></a></td>
                            <td>{{ data_get($document->template_snapshot, 'display_name', optional($document->type)->name) }}<br><small class="text-muted">{{ data_get($scenarios, $document->scenario_code.'.name', ucfirst(str_replace('_', ' ', $document->scenario_code))) }}</small></td>
                            <td>{{ optional($document->contact)->name ?: data_get($document->party_snapshot, 'name', 'Walk-in / not assigned') }}</td>
                            <td>{{ @format_date($document->issue_date) }}</td>
                            <td><span class="display_currency" data-currency_symbol="true">{{ $document->total_amount }}</span></td>
                            <td><span class="label {{ $document->status === 'void' ? 'bg-red' : ($document->status === 'draft' ? 'bg-yellow' : 'bg-green') }}">{{ ucfirst($document->status) }}</span></td>
                            <td>
                                <a class="btn btn-xs btn-primary" href="{{ route('smart-documents.show', $document) }}"><i class="fa fa-eye"></i> View</a>
                                <a class="btn btn-xs btn-default" target="_blank" rel="noopener" href="{{ route('smart-documents.preview', $document) }}" title="Preview PDF"><i class="fa fa-file-pdf-o"></i></a>
                                <a class="btn btn-xs btn-default" href="{{ route('smart-documents.download', $document) }}" title="Download PDF"><i class="fa fa-download"></i></a>
                                @if($document->status === 'draft' && (auth()->user()->can('smart_documents.update') || auth()->user()->hasRole('Admin#'.session('user.business_id')) || auth()->user()->can('superadmin')))
                                    <a class="btn btn-xs btn-default" href="{{ route('smart-documents.edit', $document) }}"><i class="fa fa-edit"></i> Edit</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center">No matching documents. Create a document to start the controlled workflow.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($documents->hasPages())<div class="box-footer clearfix">{{ $documents->links() }}</div>@endif
    </div>
</section>
@endsection
