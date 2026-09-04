@extends('layouts.app')
@section('title', 'Industry document profiles')
@section('content')
    @include('superadmin::layouts.nav')
    <section class="content-header"><h1>Industry document profiles <small>Choose the default output families for each selectable industry</small></h1></section>
    <section class="content">
        <div class="alert alert-info">
            <i class="fa fa-info-circle"></i>
            Every selectable industry should retain standard quotation, invoice, receipt and credit-note capability. Industry-specific outputs supplement those common commercial records: for example leases and rent receipts for property companies, or booking invoices, folios, events and catering for hospitality.
        </div>
        <div class="alert alert-warning">
            <i class="fa fa-shield"></i>
            This page controls document availability and terminology, not legal validity. Agreement clauses remain blank until each company supplies and explicitly approves its locally reviewed terms.
        </div>
        @foreach($industries as $industry)
            <div class="box box-primary">
                {!! Form::open(['route' => ['superadmin.industry-documents.update', $industry], 'method' => 'put']) !!}
                <div class="box-header with-border"><h3 class="box-title">{{ $industry->name }}</h3><span class="label label-default pull-right">{{ $industry->documentTypes->where('pivot.enabled_by_default', true)->count() }} enabled</span></div>
                <div class="box-body">
                    @foreach($documentTypes->groupBy('category') as $category => $types)
                        <h4>{{ ucfirst($category) }}</h4>
                        <div class="row">
                        @foreach($types as $type)
                            @php($industryType = $industry->documentTypes->firstWhere('id', $type->id))
                            <div class="col-md-4 col-sm-6">
                                <div class="checkbox"><label>{!! Form::checkbox('document_type_ids[]', $type->id, $industryType && $industryType->pivot->enabled_by_default, ['class' => 'input-icheck']) !!} <strong>{{ $type->name }}</strong><br><small class="text-muted">{{ ucfirst(str_replace('_', ' ', $type->scenario_code)) }} · {{ $type->default_prefix }}</small></label></div>
                                <input class="form-control input-sm" name="display_names[{{ $type->id }}]" value="{{ old('display_names.'.$type->id, $industryType ? $industryType->pivot->display_name : '') }}" maxlength="255" placeholder="Optional industry wording">
                            </div>
                        @endforeach
                        </div><hr>
                    @endforeach
                    <div class="checkbox"><label>{!! Form::checkbox('apply_to_existing', 1, false, ['class' => 'input-icheck']) !!} Apply these industry defaults to existing companies. Company-specific terms and document history are preserved; unavailable default types are hidden.</label></div>
                </div>
                <div class="box-footer"><button class="btn btn-primary pull-right"><i class="fa fa-save"></i> Update {{ $industry->name }}</button></div>
                {!! Form::close() !!}
            </div>
        @endforeach
    </section>
@endsection
