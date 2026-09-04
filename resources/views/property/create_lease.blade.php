@extends('layouts.app')
@section('title','Add Lease')
@section('content')
<section class="content-header"><h1>Add Lease <small>Assign a vacant unit to a tenant</small></h1></section>
<section class="content"><div class="box box-primary">
{!! Form::open(['route'=>'property.leases.store']) !!}
@include('partials.advance_deposit_guidance', [
    'advanceGuideId' => 'property-lease-advance-guide',
    'advanceGuideContext' => 'property-unit reservation or lease',
])
<div class="box-body row">
    <div class="col-md-6"><div class="form-group">{!! Form::label('property_unit_id','Location / property / vacant unit:*') !!}<select name="property_unit_id" class="form-control select2" required><option value="">Please select</option>@foreach($units as $unit)<option value="{{ $unit->id }}">{{ optional($unit->property->businessLocation)->name ?: 'Unassigned location' }} — {{ $unit->property->name }} / {{ $unit->unit_code }}</option>@endforeach</select><p class="help-block">Only units in locations available to your account are shown.</p></div></div>
    <div class="col-md-6"><div class="form-group">{!! Form::label('contact_id','Tenant:') !!}{!! Form::select('contact_id',$tenants,null,['class'=>'form-control select2','placeholder'=>'Select existing customer / tenant']) !!}</div></div>
    <div class="col-md-4"><div class="form-group">{!! Form::label('start_date','Start date:*') !!}{!! Form::date('start_date',now()->toDateString(),['class'=>'form-control','required']) !!}</div></div>
    <div class="col-md-4"><div class="form-group">{!! Form::label('end_date','End date:') !!}{!! Form::date('end_date',null,['class'=>'form-control']) !!}</div></div>
    <div class="col-md-4"><div class="form-group">{!! Form::label('monthly_rent','Monthly rent:*') !!}{!! Form::text('monthly_rent',null,['class'=>'form-control input_number','required']) !!}</div></div>
    <div class="col-md-4"><div class="form-group">{!! Form::label('security_deposit','Security Deposit (Refundable):') !!}{!! Form::text('security_deposit',0,['class'=>'form-control input_number']) !!}<p class="help-block">Held separately for damage or breach risk. It is not rent, income or an invoice payment. Payment advances are recorded after the lease is created.</p></div></div>
</div>
<div class="box-footer"><button class="btn btn-primary pull-right">Create lease</button></div>
{!! Form::close() !!}
</div></section>
@endsection
